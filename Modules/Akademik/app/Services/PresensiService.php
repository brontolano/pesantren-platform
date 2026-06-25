<?php

namespace Modules\Akademik\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Kesantrian\Models\Santri;

/**
 * F1-PWA — Presensi kelas dari PWA Guru.
 *
 * SATU sumber kebenaran tulis: mendelegasikan penulisan baris ke
 * PresensiSantriService::catat() (updateOrCreate per santri+tanggal, jalur
 * yang sama dengan resource Filament G3-18) — service ini menambahkan
 * lapisan validasi rombel + penulisan massal idempoten untuk retry PWA:
 * kirim 2x payload yang sama tetap 1 baris per santri, status terakhir menang.
 */
class PresensiService
{
    public function __construct(
        private readonly PresensiSantriService $presensiSantriService,
    ) {}

    /**
     * Catat presensi satu rombel untuk satu sesi jadwal.
     *
     * @param  array<int, array{santri_id:int|string, status:string, keterangan?:string|null}>  $entri
     * @return array<int, PresensiSantri>
     *
     * @throws ValidationException bila ada santri di luar rombel kelas sesi.
     */
    public function catatKelas(JadwalPelajaran $jadwal, array $entri, string $tanggal, ?User $pencatat = null): array
    {
        $santriIds = collect($entri)->pluck('santri_id')->map(fn ($id) => (int) $id)->all();

        // Penjagaan rombel: SEMUA santri pada payload wajib anggota kelas
        // sesi ini — menolak payload yang menyelipkan santri kelas lain
        // (klien rusak / percobaan menulis lintas kelas).
        $sah = Santri::query()
            ->whereIn('id', $santriIds)
            ->where('kelas_id', $jadwal->kelas_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $liar = array_values(array_diff($santriIds, $sah));
        if ($liar !== []) {
            throw ValidationException::withMessages([
                'presensi' => 'Santri di luar rombel kelas sesi ini: '.implode(', ', $liar),
            ]);
        }

        return DB::transaction(function () use ($jadwal, $entri, $tanggal, $pencatat): array {
            $hasil = [];

            foreach ($entri as $baris) {
                $hasil[] = $this->presensiSantriService->catat(
                    (int) $baris['santri_id'],
                    $tanggal,
                    (string) $baris['status'],
                    [
                        'kelas_id' => $jadwal->kelas_id,
                        'keterangan' => $baris['keterangan'] ?? null,
                        'dicatat_oleh' => $pencatat?->id,
                    ],
                );
            }

            return $hasil;
        });
    }

    /**
     * Status presensi rombel pada satu tanggal — peta santri_id => status,
     * dipakai layar PWA untuk menampilkan kondisi tersimpan (retry-aware).
     *
     * @return array<int, string>
     */
    public function statusKelas(JadwalPelajaran $jadwal, string $tanggal): array
    {
        return PresensiSantri::query()
            ->where('kelas_id', $jadwal->kelas_id)
            ->whereDate('tanggal', $tanggal)
            ->pluck('status', 'santri_id')
            ->all();
    }
}
