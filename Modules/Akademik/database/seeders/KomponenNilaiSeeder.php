<?php

namespace Modules\Akademik\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Akademik\Models\KomponenNilai;

/**
 * Seed komponen nilai DEFAULT tenant (pelajaran_id NULL) — Harian 40 / UTS 30 / UAS 30.
 *
 * Idempoten (firstOrCreate by nama) — aman dipanggil ulang oleh
 * tenant:reprovision. Dipanggil TenantProvisioner di dalam $tenant->run()
 * (pola LedgerSeeder); tenant_id terisi otomatis via BelongsToTenant.
 *
 * Bobot yang SUDAH diubah tenant tidak ditimpa (hanya create bila belum ada).
 */
class KomponenNilaiSeeder extends Seeder
{
    public const DEFAULTS = [
        ['nama' => 'Harian', 'bobot' => 40, 'urutan' => 1],
        ['nama' => 'UTS', 'bobot' => 30, 'urutan' => 2],
        ['nama' => 'UAS', 'bobot' => 30, 'urutan' => 3],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $komponen) {
            KomponenNilai::query()->firstOrCreate(
                ['pelajaran_id' => null, 'nama' => $komponen['nama']],
                ['bobot' => $komponen['bobot'], 'urutan' => $komponen['urutan']],
            );
        }
    }
}
