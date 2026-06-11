<?php

namespace Modules\Akademik\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\SesiUjianAkademik;
use Modules\Kesantrian\Models\Santri;
use Modules\PPDB\Models\PaketSoal;
use Modules\PPDB\Models\Soal;
use Modules\PPDB\Models\UjianAttempt;
use RuntimeException;

/**
 * F4 — Ujian Semester Online: service penghubung di atas engine ujian PPDB.
 *
 * REUSE total mekanisme PPDB (paket_soal/soal/ujian_attempt, timer
 * server-side, subset acak, auto-grade PG berbobot — rumus identik dengan
 * UjianOnlineService yang teruji) TANPA menyentuh jalur PPDB: attempt
 * akademik diikat ke santri_id + sesi_ujian_akademik_id (calon_santri_id
 * NULL) dan TIDAK menulis HasilUjian — nilai final masuk komponen nilai
 * lewat NilaiGridService (tarikKeNilai, idempoten upsert).
 */
class UjianAkademikService
{
    public function __construct(private readonly NilaiGridService $grid) {}

    /* ── Permission paket (pembuat soal per-pelajaran) ──────────────── */

    /**
     * Paket akademik hanya boleh dikelola guru pengampu pelajarannya atau
     * pemegang manage_akademik. Paket bank lembaga milik guru lain boleh
     * DIBACA/diduplikat, tidak diedit.
     *
     * @throws AuthorizationException
     */
    public function pastikanBolehKelolaPaket(User $user, int $pelajaranId, ?PaketSoal $paket = null): void
    {
        if ($user->can('manage_akademik')) {
            return;
        }

        $pengampu = Pelajaran::query()
            ->whereKey($pelajaranId)
            ->where('guru_pengampu_id', $user->getKey())
            ->exists();

        if (! $pengampu) {
            throw new AuthorizationException('Hanya guru pengampu pelajaran ini (atau manage_akademik) yang boleh mengelola paket soal akademik.');
        }

        // Bank pribadi guru lain: walau sama-sama pengampu (team teaching),
        // paket NON-bank-lembaga hanya boleh diedit pembuatnya.
        if ($paket !== null && ! $paket->is_bank_lembaga
            && $paket->created_by !== null && (int) $paket->created_by !== (int) $user->getKey()) {
            throw new AuthorizationException('Paket bank pribadi hanya boleh diedit pembuatnya.');
        }
    }

    /**
     * Buat paket akademik (pelajaran_id WAJIB; kepemilikan tercatat).
     *
     * @param  array{nama:string, durasi_menit?:int, jumlah_tampil?:?int, is_bank_lembaga?:bool}  $data
     *
     * @throws AuthorizationException
     */
    public function buatPaketAkademik(User $user, int $pelajaranId, array $data): PaketSoal
    {
        $this->ensureTenantContext();
        $this->pastikanBolehKelolaPaket($user, $pelajaranId);

        return PaketSoal::create([
            'nama' => $data['nama'],
            'kategori_ujian' => 'Akademik',
            'durasi_menit' => (int) ($data['durasi_menit'] ?? 60),
            'jumlah_tampil' => $data['jumlah_tampil'] ?? null,
            'is_active' => true,
            'pelajaran_id' => $pelajaranId,
            'created_by' => $user->getKey(),
            'is_bank_lembaga' => (bool) ($data['is_bank_lembaga'] ?? false),
        ]);
    }

    /** Duplikat paket bank lembaga menjadi paket pribadi guru pemanggil. */
    public function duplikatDariBankLembaga(User $user, PaketSoal $sumber, int $pelajaranId): PaketSoal
    {
        $this->ensureTenantContext();

        if (! $sumber->is_bank_lembaga) {
            throw new AuthorizationException('Hanya paket bank lembaga yang boleh diduplikat guru lain.');
        }
        $this->pastikanBolehKelolaPaket($user, $pelajaranId);

        $baru = $this->buatPaketAkademik($user, $pelajaranId, [
            'nama' => $sumber->nama.' (salinan)',
            'durasi_menit' => $sumber->durasi_menit,
            'jumlah_tampil' => $sumber->jumlah_tampil,
        ]);

        foreach ($sumber->soal as $s) {
            $baru->soal()->create($s->only(['tipe', 'pertanyaan', 'pilihan', 'kunci', 'bobot', 'sort']));
        }

        return $baru;
    }

    /* ── Attempt santri (REUSE mekanisme PPDB) ──────────────────────── */

    /**
     * Mulai/lanjutkan attempt santri pada sesi rombelnya. Idempoten:
     * attempt berjalan dikembalikan apa adanya (resume, timer TIDAK reset).
     *
     * @throws AuthorizationException santri bukan anggota kelas sesi
     * @throws InvalidArgumentException sesi tutup / sudah selesai
     */
    public function mulai(Santri $santri, SesiUjianAkademik $sesi): UjianAttempt
    {
        $this->ensureTenantContext();

        if ((int) $santri->kelas_id !== (int) $sesi->kelas_id) {
            throw new AuthorizationException('Sesi ujian ini bukan untuk kelas Anda.');
        }
        if (! $sesi->sedangBerlangsung()) {
            throw new InvalidArgumentException('Sesi ujian tidak sedang dibuka.');
        }

        $paket = $sesi->paket;
        if ($paket === null || ! $paket->is_active) {
            throw new InvalidArgumentException('Paket soal tidak aktif.');
        }

        $existing = UjianAttempt::query()
            ->where('santri_id', $santri->id)
            ->where('sesi_ujian_akademik_id', $sesi->id)
            ->first();
        if ($existing) {
            if ($existing->status !== 'berjalan') {
                throw new InvalidArgumentException('Ujian sudah diselesaikan — tidak bisa diulang.');
            }

            return $existing;
        }

        $ids = $paket->soal()->pluck('id');
        if ($ids->isEmpty()) {
            throw new InvalidArgumentException('Paket belum memiliki soal.');
        }
        if ($paket->jumlah_tampil && $paket->jumlah_tampil < $ids->count()) {
            $ids = $ids->shuffle()->take($paket->jumlah_tampil)->values();
        }

        // Deadline server-side: durasi paket, dipotong batas tutup sesi.
        $deadline = now()->addMinutes((int) $paket->durasi_menit);
        if ($deadline->greaterThan($sesi->selesai_at)) {
            $deadline = $sesi->selesai_at->copy();
        }

        try {
            return UjianAttempt::create([
                'santri_id' => $santri->id,
                'sesi_ujian_akademik_id' => $sesi->id,
                'paket_soal_id' => $paket->id,
                'soal_tampil' => $ids->all(),
                'jawaban' => [],
                'mulai_at' => now(),
                'deadline_at' => $deadline,
                'status' => 'berjalan',
            ]);
        } catch (UniqueConstraintViolationException) {
            // Balapan dua request "mulai" bersamaan: ambil attempt yang
            // menang balapan, jangan 500 ke santri.
            $existing = UjianAttempt::query()
                ->where('santri_id', $santri->id)
                ->where('sesi_ujian_akademik_id', $sesi->id)
                ->firstOrFail();
            if ($existing->status !== 'berjalan') {
                throw new InvalidArgumentException('Ujian sudah diselesaikan — tidak bisa diulang.');
            }

            return $existing;
        }
    }

    /** Simpan satu jawaban — aturan identik jalur PPDB. */
    public function jawab(UjianAttempt $attempt, int $soalId, string $jawaban): UjianAttempt
    {
        $this->ensureTenantContext();

        // Read-modify-write peta jawaban HARUS di bawah lock baris: dua
        // autosave paralel tanpa lock saling menimpa (lost update).
        return DB::transaction(function () use ($attempt, $soalId, $jawaban): UjianAttempt {
            $locked = UjianAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'berjalan') {
                throw new InvalidArgumentException('Ujian sudah selesai.');
            }
            if (now()->greaterThan($locked->deadline_at)) {
                throw new InvalidArgumentException('Waktu ujian habis — jawaban tidak diterima.');
            }
            if (! in_array($soalId, (array) $locked->soal_tampil, true)) {
                throw new InvalidArgumentException('Soal bukan bagian dari ujian ini.');
            }

            $map = (array) $locked->jawaban; // re-read di dalam lock → merge aman
            $map[(string) $soalId] = $jawaban;
            $locked->update(['jawaban' => $map]);

            return $locked->fresh();
        });
    }

    /**
     * Selesaikan attempt: auto-grade PG berbobot (rumus identik PPDB);
     * essay → status menunggu_nilai. TIDAK menulis HasilUjian (itu jalur
     * PPDB) — nilai masuk komponen lewat tarikKeNilai.
     */
    public function selesaikan(UjianAttempt $attempt): UjianAttempt
    {
        $this->ensureTenantContext();

        return DB::transaction(function () use ($attempt): UjianAttempt {
            $locked = UjianAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'berjalan') {
                return $locked; // idempoten (double-submit)
            }

            $soal = Soal::query()->whereIn('id', (array) $locked->soal_tampil)->get();
            $jawaban = (array) $locked->jawaban;

            $bobotPg = 0;
            $skorPg = 0;
            $adaEssay = false;
            foreach ($soal as $s) {
                if ($s->tipe === 'pg') {
                    $bobotPg += $s->bobot;
                    $dijawab = strtoupper(trim((string) ($jawaban[(string) $s->id] ?? '')));
                    if ($dijawab !== '' && $dijawab === strtoupper((string) $s->kunci)) {
                        $skorPg += $s->bobot;
                    }
                } else {
                    $adaEssay = true;
                }
            }

            $nilaiPg = $bobotPg > 0 ? round($skorPg / $bobotPg * 100, 2) : null;

            $locked->update([
                'selesai_at' => now(),
                'nilai_pg' => $nilaiPg,
                'status' => $adaEssay ? 'menunggu_nilai' : 'selesai',
                'nilai_final' => $adaEssay ? null : $nilaiPg,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Nilai manual essay oleh guru: $skor = [soal_id => 0..bobot]. Final
     * tertimbang bobot semua soal tampil (rumus identik PPDB).
     *
     * @throws AuthorizationException bukan pengampu pelajaran sesi
     */
    public function nilaiEssay(User $guru, UjianAttempt $attempt, array $skor): UjianAttempt
    {
        $this->ensureTenantContext();

        $sesi = $attempt->sesiAkademik;
        if ($sesi === null) {
            throw new InvalidArgumentException('Attempt ini bukan attempt akademik.');
        }
        $this->pastikanBolehKelolaPaket($guru, (int) $sesi->pelajaran_id, null);

        if ($attempt->status !== 'menunggu_nilai') {
            throw new InvalidArgumentException('Attempt tidak menunggu penilaian essay.');
        }

        $soal = Soal::query()->whereIn('id', (array) $attempt->soal_tampil)->get();
        $jawaban = (array) $attempt->jawaban;

        $bobotTotal = 0;
        $skorTotal = 0;
        $bobotEssay = 0;
        $skorEssay = 0;
        foreach ($soal as $s) {
            $bobotTotal += $s->bobot;
            if ($s->tipe === 'pg') {
                $dijawab = strtoupper(trim((string) ($jawaban[(string) $s->id] ?? '')));
                if ($dijawab !== '' && $dijawab === strtoupper((string) $s->kunci)) {
                    $skorTotal += $s->bobot;
                }
            } else {
                $bobotEssay += $s->bobot;
                $nilai = (float) ($skor[$s->id] ?? $skor[(string) $s->id] ?? 0);
                if ($nilai < 0 || $nilai > $s->bobot) {
                    throw new InvalidArgumentException("Skor soal #{$s->id} harus 0..{$s->bobot}.");
                }
                $skorTotal += $nilai;
                $skorEssay += $nilai;
            }
        }

        $attempt->update([
            'nilai_essay' => $bobotEssay > 0 ? round($skorEssay / $bobotEssay * 100, 2) : null,
            'nilai_final' => $bobotTotal > 0 ? round($skorTotal / $bobotTotal * 100, 2) : 0,
            'status' => 'selesai',
        ]);

        return $attempt->fresh();
    }

    /**
     * Sweep: attempt 'berjalan' yang deadline-nya lewat → finalisasi lewat
     * selesaikan() (jalur skor teruji; lock + idempoten). Dipanggil dari
     * tarikKeNilai dan polling pengawas agar attempt kedaluwarsa tidak
     * menggantung selamanya tanpa nilai.
     *
     * @return int jumlah attempt yang difinalisasi
     */
    public function finalisasiKedaluwarsa(SesiUjianAkademik $sesi): int
    {
        $this->ensureTenantContext();

        $kedaluwarsa = UjianAttempt::query()
            ->where('sesi_ujian_akademik_id', $sesi->id)
            ->where('status', 'berjalan')
            ->where('deadline_at', '<', now())
            ->get();

        foreach ($kedaluwarsa as $attempt) {
            $this->selesaikan($attempt);
        }

        return $kedaluwarsa->count();
    }

    /* ── Tarik ke Nilai (komponen) ──────────────────────────────────── */

    /**
     * Tarik nilai final semua attempt selesai pada sesi → komponen nilai
     * terpilih, via NilaiGridService::simpanBaris (F0/F2 — SATU jalur
     * persist). Idempoten: upsert by (santri, pelajaran, periode) + merge
     * nilai_komponen existing → tarik 2× = nilai sama.
     *
     * @return int jumlah baris nilai tertulis
     */
    public function tarikKeNilai(SesiUjianAkademik $sesi, int $komponenId, ?User $actor = null): int
    {
        $this->ensureTenantContext();

        if ($actor !== null) {
            $this->pastikanBolehKelolaPaket($actor, (int) $sesi->pelajaran_id, null);
        }

        // Komponen WAJIB anggota daftar komponen pelajaran sesi (sumber yang
        // sama dengan picker di UI) — tolak id komponen pelajaran lain.
        $komponenValid = app(NilaiService::class)->komponenUntukPelajaran((int) $sesi->pelajaran_id)
            ->pluck('id')->map(fn ($id): int => (int) $id);
        if (! $komponenValid->contains($komponenId)) {
            throw new InvalidArgumentException('Komponen nilai tidak valid untuk pelajaran sesi ini.');
        }

        // Finalisasi dulu attempt kedaluwarsa agar ikut tertarik.
        $this->finalisasiKedaluwarsa($sesi);

        $periodeId = $sesi->periode_akademik_id
            ?? $this->grid->periodeAktif($sesi->kelas)?->id;
        if ($periodeId === null) {
            throw new InvalidArgumentException('Sesi tidak punya periode akademik dan tidak ada periode aktif.');
        }

        $attempts = UjianAttempt::query()
            ->where('sesi_ujian_akademik_id', $sesi->id)
            ->where('status', 'selesai')
            ->whereNotNull('nilai_final')
            ->whereNotNull('santri_id')
            ->get();

        $jumlah = 0;
        foreach ($attempts as $attempt) {
            $existing = Nilai::query()
                ->where('santri_id', $attempt->santri_id)
                ->where('pelajaran_id', $sesi->pelajaran_id)
                ->where('periode_akademik_id', $periodeId)
                ->first();

            $komponen = (array) ($existing?->nilai_komponen ?? []);
            $komponen[(string) $komponenId] = (float) $attempt->nilai_final;

            $this->grid->simpanBaris([
                'santri_id' => $attempt->santri_id,
                'pelajaran_id' => $sesi->pelajaran_id,
                'periode_akademik_id' => $periodeId,
                'kelas_id' => $sesi->kelas_id,
                'nilai_komponen' => $komponen,
            ], $actor);

            $jumlah++;
        }

        return $jumlah;
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
