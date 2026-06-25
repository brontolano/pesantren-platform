<?php

namespace Modules\PPDB\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\PaketSoal;
use Modules\PPDB\Models\Soal;
use Modules\PPDB\Models\UjianAttempt;
use RuntimeException;

/**
 * Ujian online PPDB: mulai (subset acak, deadline server) → jawab (hingga
 * deadline) → selesaikan (auto-grade PG berbobot; essay menunggu nilai
 * manual) → nilaiEssay (final tertimbang bobot, tulis HasilUjian).
 *
 * Nilai 0..100 = Σ(skor terbobot) / Σ(bobot soal tampil) × 100.
 * Satu attempt per calon per paket (unique index) — resume, bukan mulai ulang.
 */
class UjianOnlineService
{
    public function __construct(private readonly PpdbExamResultService $hasil) {}

    /** Mulai/lanjutkan attempt. Idempoten: attempt berjalan dikembalikan apa adanya. */
    public function mulai(CalonSantri $calon, PaketSoal $paket): UjianAttempt
    {
        $this->ensureTenantContext();

        if (! $paket->is_active) {
            throw new InvalidArgumentException('Paket soal tidak aktif.');
        }

        $existing = UjianAttempt::query()
            ->where('calon_santri_id', $calon->id)
            ->where('paket_soal_id', $paket->id)
            ->first();
        if ($existing) {
            if ($existing->status !== 'berjalan') {
                throw new InvalidArgumentException('Ujian sudah diselesaikan — tidak bisa diulang.');
            }

            return $existing; // resume (refresh halaman ≠ mulai ulang/timer reset)
        }

        $ids = $paket->soal()->pluck('id');
        if ($ids->isEmpty()) {
            throw new InvalidArgumentException('Paket belum memiliki soal.');
        }
        if ($paket->jumlah_tampil && $paket->jumlah_tampil < $ids->count()) {
            $ids = $ids->shuffle()->take($paket->jumlah_tampil)->values();
        }

        return UjianAttempt::create([
            'calon_santri_id' => $calon->id,
            'paket_soal_id' => $paket->id,
            'soal_tampil' => $ids->all(),
            'jawaban' => [],
            'mulai_at' => now(),
            'deadline_at' => now()->addMinutes((int) $paket->durasi_menit),
            'status' => 'berjalan',
        ]);
    }

    /** Simpan satu jawaban. Ditolak setelah deadline / soal di luar attempt. */
    public function jawab(UjianAttempt $attempt, int $soalId, string $jawaban): UjianAttempt
    {
        $this->ensureTenantContext();

        if ($attempt->status !== 'berjalan') {
            throw new InvalidArgumentException('Ujian sudah selesai.');
        }
        if (now()->greaterThan($attempt->deadline_at)) {
            throw new InvalidArgumentException('Waktu ujian habis — jawaban tidak diterima.');
        }
        if (! in_array($soalId, (array) $attempt->soal_tampil, true)) {
            throw new InvalidArgumentException('Soal bukan bagian dari ujian ini.');
        }

        $jawabanMap = (array) $attempt->jawaban;
        $jawabanMap[(string) $soalId] = $jawaban;
        $attempt->update(['jawaban' => $jawabanMap]);

        return $attempt->fresh();
    }

    /**
     * Selesaikan attempt (dipanggil peserta ATAU otomatis lewat deadline).
     * Auto-grade PG; bila ada essay → status menunggu_nilai (HasilUjian
     * BARU ditulis setelah essay dinilai agar nilai tak berubah-ubah).
     */
    public function selesaikan(UjianAttempt $attempt): UjianAttempt
    {
        $this->ensureTenantContext();

        return DB::transaction(function () use ($attempt): UjianAttempt {
            $locked = UjianAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'berjalan') {
                return $locked; // idempoten (double-submit selesai)
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

            if (! $adaEssay && $nilaiPg !== null) {
                $this->tulisHasil($locked->fresh());
            }

            return $locked->fresh();
        });
    }

    /**
     * Nilai manual essay: $skor = [soal_id => skor 0..bobot]. Nilai final =
     * Σ(skor PG terbobot + skor essay) / Σ(bobot SEMUA soal tampil) × 100.
     */
    public function nilaiEssay(UjianAttempt $attempt, array $skor): UjianAttempt
    {
        $this->ensureTenantContext();

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

        $this->tulisHasil($attempt->fresh());

        return $attempt->fresh();
    }

    /** Catat ke hasil_ujian (dasar keputusan) lewat service teruji + kuncinya. */
    private function tulisHasil(UjianAttempt $attempt): void
    {
        $this->hasil->inputResult($attempt->calonSantri, [
            'kategori_ujian' => (string) $attempt->paket->kategori_ujian,
            'nilai' => (float) $attempt->nilai_final,
            'catatan' => 'Ujian online paket: '.$attempt->paket->nama,
        ]);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
