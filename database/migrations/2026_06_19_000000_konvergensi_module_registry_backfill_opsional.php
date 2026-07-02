<?php

use App\Models\Tenant;
use Database\Seeders\ModuleRegistrySeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F1 — Konvergensi katalog module_registry (CENTRAL) + backfill AMAN tenant_modules
 * untuk modul opsional 'crm' & 'koperasi'.
 *
 * LATAR (PR#223): key 'crm'/'koperasi' hanya masuk registry lewat
 * ModuleRegistrySeeder, sedangkan deploy hanya menjalankan `migrate` (tidak seed).
 * Gerbang ModuleService::isVisibleForTenant() memperlakukan key yang TAK terdaftar
 * sebagai "terlihat" (fail-open) → bila seeder tak dijalankan manual di prod, menu
 * CRM/Inventory malah TETAP tampil — kebalikan dari maksud #223. Migrasi ini
 * mengOTOMATISkan konvergensi registry pada setiap deploy sehingga gerbang menjadi
 * deterministik tanpa langkah manual.
 *
 * NON-DISRUPTIF ("jangan mengganggu modul yang sudah ada"):
 *  - Bagian 1 hanya meng-upsert katalog (idempoten via updateOrCreate by key);
 *    no-op untuk key yang sudah benar, hanya menambah yang hilang.
 *  - Bagian 2 hanya MENGAKTIFKAN 'crm'/'koperasi' bagi tenant yang TERBUKTI sudah
 *    memakainya (punya data POS/inventory/CRM di DB tenant-nya), supaya modul yang
 *    sedang dipakai tidak hilang dari sidebar saat gerbang diberlakukan. TIDAK
 *    PERNAH menonaktifkan modul apa pun dan tidak menyentuh modul lain. Tenant tanpa
 *    data POS/CRM mengikuti perilaku #223 (default mati, bisa dipasang via Pusat Modul).
 */
return new class extends Migration
{
    /** Tabel penanda pemakaian per modul opsional (dibaca di DB tenant). */
    private const PEMAKAIAN = [
        'koperasi' => ['inventory_item', 'pos_transaksi', 'pos_profil', 'pos_sesi'],
        'crm' => ['crm_penawaran', 'crm_aktivitas'],
    ];

    public function up(): void
    {
        // (1) Konvergensi katalog registry (idempoten, sumber kebenaran tunggal).
        if (class_exists(ModuleRegistrySeeder::class)) {
            (new ModuleRegistrySeeder())->run();
        }

        // (2) Backfill aman: kumpulkan tenant yang sudah memakai crm/koperasi.
        $rencana = []; // tenant_id => list<string> modul yang perlu diaktifkan

        foreach (Tenant::all() as $tenant) {
            try {
                $pakai = $tenant->run(function (): array {
                    $aktif = [];
                    foreach (self::PEMAKAIAN as $modul => $tabel) {
                        foreach ($tabel as $t) {
                            if (Schema::hasTable($t) && DB::table($t)->exists()) {
                                $aktif[] = $modul;
                                break;
                            }
                        }
                    }

                    return $aktif;
                });
            } catch (\Throwable) {
                // DB tenant belum termigrasi / tak terjangkau → lewati tenant ini,
                // jangan gagalkan seluruh migrasi.
                continue;
            }

            if (! empty($pakai)) {
                $rencana[$tenant->getKey()] = array_unique($pakai);
            }
        }

        // (3) Tulis ke CENTRAL setelah konteks tenancy kembali (tenant_modules central).
        $central = config('tenancy.database.central_connection', config('database.default'));
        $now = now();

        foreach ($rencana as $tid => $modul) {
            foreach ($modul as $m) {
                $row = DB::connection($central)->table('tenant_modules')
                    ->where('tenant_id', $tid)
                    ->where('module_name', $m)
                    ->first();

                if ($row === null) {
                    DB::connection($central)->table('tenant_modules')->insert([
                        'tenant_id' => $tid,
                        'module_name' => $m,
                        'is_active' => true,
                        'installed_at' => $now,
                        'configured_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } elseif (! $row->is_active) {
                    // HANYA mengaktifkan; pertahankan timestamp lama bila sudah ada.
                    DB::connection($central)->table('tenant_modules')
                        ->where('id', $row->id)
                        ->update([
                            'is_active' => true,
                            'installed_at' => $row->installed_at ?? $now,
                            'configured_at' => $row->configured_at ?? $now,
                            'updated_at' => $now,
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Tanpa rollback otomatis: membatalkan konvergensi katalog atau menonaktifkan
        // modul yang terbukti dipakai berisiko menyembunyikan modul aktif. Bila perlu,
        // kelola lewat Pusat Modul.
    }
};
