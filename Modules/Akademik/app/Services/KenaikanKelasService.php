<?php

namespace Modules\Akademik\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Akademik\Models\KeputusanKenaikan;
use Modules\Akademik\Models\Lembaga;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\PenempatanKelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\DisiplinService;
use Modules\Tahfidz\Models\HafalanTahfidz;
use RuntimeException;

/**
 * WP-07 — Mesin rekomendasi kenaikan kelas + eksekusi hasil sidang.
 *
 * POLICY-FIRST: semua ambang adalah Setting per lembaga
 * (`akademik.kenaikan.{lembaga_id}`, fallback `akademik.kenaikan.default`,
 * fallback hard default) — dua tenant/lembaga boleh berbeda kebijakan.
 *
 * Mesin hanya MEREKOMENDASIKAN; keputusan final tetap di rapat (manusia).
 */
class KenaikanKelasService
{
    public const SETTING_PREFIX = 'akademik.kenaikan.';

    public const SETTING_MODE_PENEMPATAN = 'akademik.penempatan_mode';

    public const DEFAULTS = [
        'min_nilai' => 60,
        'min_kehadiran_persen' => 75,
        'maks_poin_disiplin' => 100,
        'syarat_tahfidz' => false,      // bila true: wajib >= min_setoran_tahfidz hafalan selesai
        'min_setoran_tahfidz' => 1,
    ];

    /**
     * Ambang kebijakan kenaikan utk satu lembaga (NULL = lembaga default).
     *
     * @return array{min_nilai: float, min_kehadiran_persen: float, maks_poin_disiplin: int, syarat_tahfidz: bool, min_setoran_tahfidz: int}
     */
    public function ambang(?Lembaga $lembaga): array
    {
        $perLembaga = $lembaga
            ? Setting::getTenantValue(self::SETTING_PREFIX.$lembaga->id)
            : null;
        $default = Setting::getTenantValue(self::SETTING_PREFIX.'default');

        $merged = array_merge(
            self::DEFAULTS,
            is_array($default) ? $default : [],
            is_array($perLembaga) ? $perLembaga : [],
        );

        return [
            'min_nilai' => (float) $merged['min_nilai'],
            'min_kehadiran_persen' => (float) $merged['min_kehadiran_persen'],
            'maks_poin_disiplin' => (int) $merged['maks_poin_disiplin'],
            'syarat_tahfidz' => (bool) $merged['syarat_tahfidz'],
            'min_setoran_tahfidz' => (int) $merged['min_setoran_tahfidz'],
        ];
    }

    /** Mode penempatan: 'berjenjang' (default) | 'bebas' (mu'adalah). */
    public function modePenempatan(): string
    {
        $mode = Setting::getTenantStringValue(self::SETTING_MODE_PENEMPATAN);

        return $mode === 'bebas' ? 'bebas' : 'berjenjang';
    }

    /**
     * Evaluasi satu santri pada satu periode. Santri dievaluasi di KELAS
     * TERAKHIRNYA (penempatan is_current — pindah tengah tahun aman).
     *
     * @return array{rekomendasi: 'naik'|'tinggal'|'lulus', alasan: array<int, string>}
     */
    public function evaluasi(Santri $santri, PeriodeAkademik $periode): array
    {
        $kelas = $this->kelasTerakhir($santri);

        if (! $kelas) {
            return ['rekomendasi' => 'tinggal', 'alasan' => ['Santri tidak punya penempatan kelas aktif.']];
        }

        $lembaga = $kelas->lembaga;
        $ambang = $this->ambang($lembaga);
        $alasan = [];

        // 1) Rata-rata nilai akhir (rumus tunggal: NilaiService::hitungNilaiAkhir)
        $rata = $this->rataNilaiAkhir($santri, $periode);
        if ($rata === null) {
            $alasan[] = 'Belum ada nilai pada periode ini.';
        } elseif ($rata < $ambang['min_nilai']) {
            $alasan[] = sprintf('Rata-rata nilai %.2f di bawah ambang %.2f.', $rata, $ambang['min_nilai']);
        }

        // 2) Kehadiran vs hari efektif kalender (WP-02)
        $kehadiran = $this->persenKehadiran($santri, $periode, $lembaga);
        if ($kehadiran !== null && $kehadiran < $ambang['min_kehadiran_persen']) {
            $alasan[] = sprintf('Kehadiran %.1f%% di bawah ambang %.1f%%.', $kehadiran, $ambang['min_kehadiran_persen']);
        }

        // 3) Poin disiplin (WP-09)
        $poin = app(DisiplinService::class)->totalPoin($santri, $periode->tanggal_akhir);
        if ($poin > $ambang['maks_poin_disiplin']) {
            $alasan[] = sprintf('Poin disiplin %d melampaui batas %d.', $poin, $ambang['maks_poin_disiplin']);
        }

        // 4) Capaian tahfidz — HANYA bila lembaga mensyaratkan
        if ($ambang['syarat_tahfidz']) {
            $setoran = HafalanTahfidz::query()
                ->where('santri_id', $santri->id)
                ->where('status', 'selesai')
                ->whereBetween('tanggal_selesai', [$periode->tanggal_mulai, $periode->tanggal_akhir])
                ->count();

            if ($setoran < $ambang['min_setoran_tahfidz']) {
                $alasan[] = sprintf('Capaian tahfidz %d setoran selesai, syarat minimal %d.', $setoran, $ambang['min_setoran_tahfidz']);
            }
        }

        if ($alasan !== []) {
            return ['rekomendasi' => 'tinggal', 'alasan' => $alasan];
        }

        $lulus = $this->diTingkatTertinggi($kelas);

        return [
            'rekomendasi' => $lulus ? 'lulus' : 'naik',
            'alasan' => [$lulus
                ? 'Memenuhi semua kriteria dan berada di tingkat tertinggi lembaga.'
                : 'Memenuhi semua kriteria kenaikan.'],
        ];
    }

    /**
     * Kelas terakhir santri = penempatan is_current (sumber kebenaran WP-05);
     * fallback kelas_id kolom santri (data lama tanpa penempatan).
     */
    public function kelasTerakhir(Santri $santri): ?Kelas
    {
        $penempatan = PenempatanKelas::query()
            ->where('santri_id', $santri->id)
            ->where('is_current', true)
            ->orderByDesc('tanggal_penempatan')
            ->orderByDesc('id')
            ->first();

        if ($penempatan) {
            return Kelas::query()->find($penempatan->kelas_id);
        }

        return $santri->kelas_id ? Kelas::query()->find($santri->kelas_id) : null;
    }

    /** Rata-rata nilai_akhir santri pada periode (NULL = belum ada nilai). */
    public function rataNilaiAkhir(Santri $santri, PeriodeAkademik $periode): ?float
    {
        $rows = Nilai::query()
            ->where('santri_id', $santri->id)
            ->where('periode_akademik_id', $periode->id)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $svc = app(NilaiService::class);

        $nilai = $rows->map(function (Nilai $n) use ($svc): float {
            return $n->nilai_akhir !== null
                ? (float) $n->nilai_akhir
                : $svc->hitungNilaiAkhir($n->getAttributes());
        });

        return round((float) $nilai->avg(), 2);
    }

    /**
     * % kehadiran = hadir efektif ÷ hari efektif kalender (WP-02).
     * NULL bila hari efektif 0 ATAU santri tidak punya baris presensi sama
     * sekali pada periode — JANGAN gugurkan santri karena DATA kosong
     * (kalender/presensi belum dipakai tenant), hanya karena alpa nyata.
     */
    public function persenKehadiran(Santri $santri, PeriodeAkademik $periode, ?Lembaga $lembaga): ?float
    {
        $hariEfektif = app(KalenderService::class)->hariEfektifDalamRentang(
            $periode->tanggal_mulai->copy(),
            $periode->tanggal_akhir->copy(),
            $lembaga,
        );

        if ($hariEfektif <= 0) {
            return null;
        }

        $presensi = PresensiSantri::query()
            ->where('santri_id', $santri->id)
            ->whereBetween('tanggal', [$periode->tanggal_mulai, $periode->tanggal_akhir]);

        if (! (clone $presensi)->exists()) {
            return null;
        }

        $hadir = (clone $presensi)
            ->whereIn('status', PresensiSantri::HADIR_EFEKTIF)
            ->count();

        return round(min(100.0, $hadir / $hariEfektif * 100), 2);
    }

    /** Apakah kelas berada di tingkat tertinggi lembaganya? */
    public function diTingkatTertinggi(Kelas $kelas): bool
    {
        if ($kelas->tingkat === null || $kelas->tingkat === '') {
            return false;
        }

        $tingkatLembaga = Kelas::query()
            ->where('is_active', true)
            ->when(
                $kelas->lembaga_id !== null,
                fn ($q) => $q->where('lembaga_id', $kelas->lembaga_id),
                fn ($q) => $q->whereNull('lembaga_id'),
            )
            ->pluck('tingkat')
            ->filter(fn ($t) => $t !== null && $t !== '')
            ->unique()
            ->values();

        if ($tingkatLembaga->isEmpty()) {
            return false;
        }

        $tertinggi = $tingkatLembaga->every(fn ($t) => is_numeric($t))
            ? (string) $tingkatLembaga->map(fn ($t) => (float) $t)->max()
            : (string) $tingkatLembaga->sort()->last();

        return is_numeric($kelas->tingkat) && is_numeric($tertinggi)
            ? (float) $kelas->tingkat === (float) $tertinggi
            : (string) $kelas->tingkat === $tertinggi;
    }

    /**
     * Opsi kelas tujuan utk kelas asal sesuai mode penempatan:
     * - berjenjang: hanya kelas se-lembaga di tingkat numerik berikutnya;
     * - bebas (mu'adalah): semua kelas aktif se-lembaga (lompat/turun sah).
     *
     * @return Collection<int, Kelas>
     */
    public function kelasTujuanOptions(Kelas $asal): Collection
    {
        $q = Kelas::query()
            ->where('is_active', true)
            ->when(
                $asal->lembaga_id !== null,
                fn ($qq) => $qq->where('lembaga_id', $asal->lembaga_id),
                fn ($qq) => $qq->whereNull('lembaga_id'),
            );

        if ($this->modePenempatan() === 'bebas') {
            return $q->orderBy('tingkat')->orderBy('nama_kelas')->get();
        }

        if (is_numeric($asal->tingkat)) {
            $next = (string) ((int) $asal->tingkat + 1);

            return $q->where('tingkat', $next)->orderBy('nama_kelas')->get();
        }

        // Tingkat non-numerik: tak bisa dihitung "berikutnya" — kembalikan
        // semua selain kelas asal, biarkan rapat memilih.
        return $q->where('id', '!=', $asal->id)->orderBy('tingkat')->orderBy('nama_kelas')->get();
    }

    /**
     * Simpan/refresh DRAFT keputusan satu santri (upsert per santri+periode).
     * Baris yang SUDAH final tidak boleh diubah lagi.
     */
    public function simpanDraft(array $data, ?User $actor = null): KeputusanKenaikan
    {
        if (! in_array($data['keputusan'], KeputusanKenaikan::KEPUTUSAN, true)) {
            throw new RuntimeException('Keputusan tidak dikenal: '.$data['keputusan']);
        }

        $payload = [
            'kelas_asal_id' => $data['kelas_asal_id'],
            'kelas_tujuan_id' => $data['keputusan'] === 'naik' ? ($data['kelas_tujuan_id'] ?? null) : null,
            'rekomendasi' => $data['rekomendasi'],
            'keputusan' => $data['keputusan'],
            'alasan' => $data['alasan'] ?? [],
            'catatan' => $data['catatan'] ?? null,
            'diputuskan_oleh' => $actor?->getKey(),
        ];

        // Transaksi + lockForUpdate: finalisasi paralel tak bisa menyelinap
        // antara cek finalized_at dan save (TOCTOU).
        return DB::transaction(function () use ($data, $payload): KeputusanKenaikan {
            $existing = KeputusanKenaikan::query()
                ->where('santri_id', $data['santri_id'])
                ->where('periode_akademik_id', $data['periode_akademik_id'])
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->finalized_at !== null) {
                throw new RuntimeException('Keputusan sudah difinalisasi — tidak bisa diubah.');
            }

            if ($existing) {
                $existing->fill($payload)->save();

                return $existing->fresh();
            }

            try {
                return KeputusanKenaikan::create($payload + [
                    'santri_id' => $data['santri_id'],
                    'periode_akademik_id' => $data['periode_akademik_id'],
                ]);
            } catch (QueryException $e) {
                if ($e->getCode() !== '23505') {
                    throw $e;
                }
                // Balapan create paralel — baris sudah ada; ulangi sebagai update.
                throw new RuntimeException('Baris keputusan sedang disimpan proses lain — muat ulang lalu coba lagi.');
            }
        });
    }

    /**
     * Finalisasi hasil sidang satu periode (opsional dibatasi satu kelas
     * asal) dalam SATU transaksi DB:
     * - naik    → PenempatanKelas baru ke kelas tujuan (observer WP-05
     *             menutup penempatan lama + sinkron santri.kelas_id);
     * - tinggal → TANPA penempatan baru (penempatan lama tetap is_current —
     *             konsisten dengan observer, tidak ada baris duplikat);
     * - lulus   → hanya simpan keputusan (proses alumni = WP-08).
     *
     * IDEMPOTEN: baris ber-finalized_at dilewati — finalisasi 2× tidak
     * menduplikasi penempatan.
     *
     * @return int jumlah keputusan yang baru difinalisasi
     */
    public function finalisasi(PeriodeAkademik $periode, ?Kelas $kelasAsal, User $actor): int
    {
        return DB::transaction(function () use ($periode, $kelasAsal, $actor): int {
            $draft = KeputusanKenaikan::query()
                ->where('periode_akademik_id', $periode->id)
                ->when($kelasAsal, fn ($q) => $q->where('kelas_asal_id', $kelasAsal->id))
                ->whereNull('finalized_at')
                ->lockForUpdate()
                ->get();

            $jumlah = 0;

            foreach ($draft as $keputusan) {
                if ($keputusan->keputusan === 'naik') {
                    if (! $keputusan->kelas_tujuan_id) {
                        throw new RuntimeException(
                            "Keputusan 'naik' santri #{$keputusan->santri_id} tanpa kelas tujuan.",
                        );
                    }

                    PenempatanKelas::create([
                        'santri_id' => $keputusan->santri_id,
                        'kelas_id' => $keputusan->kelas_tujuan_id,
                        'semester' => $periode->semester,
                        'tahun_akademik' => $periode->tahun_akademik,
                        'tanggal_penempatan' => now()->toDateString(),
                        'alasan_mutasi' => 'Kenaikan kelas — '.$periode->nama,
                        'is_current' => true,
                    ]);
                }

                $keputusan->forceFill([
                    'finalized_at' => now(),
                    'diputuskan_oleh' => $actor->getKey(),
                ])->save();

                $jumlah++;
            }

            return $jumlah;
        });
    }
}
