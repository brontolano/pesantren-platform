<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupSantri extends Command
{
    protected $signature = 'cleanup:santri {--tenant-id=2}';

    protected $description = 'Delete all santri data for a specific tenant (asy-syifaa by default)';

    public function handle()
    {
        $tenantId = $this->option('tenant-id');
        $this->line("Initializing tenant {$tenantId}...");

        try {
            $tenant = \App\Models\Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("Tenant {$tenantId} not found");
                return 1;
            }

            tenancy()->initialize($tenant);
            $this->line("Tenant: " . tenant('name'));

            // Count sebelum
            $before = \Modules\Kesantrian\Models\Santri::count();
            $this->line("Santri sebelum: {$before}");

            // Delete CASCADE
            \Modules\Kesantrian\Models\Santri::truncate();
            $this->line("✓ Santri deleted");

            // Delete related data — skip tables yang belum ada di tenant ini
            if (\Schema::hasTable('keuangan.pembayaran')) {
                \DB::table('keuangan.pembayaran')->delete();
                $this->line("✓ Pembayaran deleted");
            }

            if (\Schema::hasTable('akademik.nilai')) {
                \DB::table('akademik.nilai')->delete();
                $this->line("✓ Nilai deleted");
            }

            if (\Schema::hasTable('akademik.presensi')) {
                \DB::table('akademik.presensi')->delete();
                $this->line("✓ Presensi deleted");
            }

            if (\Schema::hasTable('keuangan.tabungan')) {
                \DB::table('keuangan.tabungan')->delete();
                $this->line("✓ Tabungan deleted");
            }

            if (\Schema::hasTable('akademik.hafalan_tahfidz')) {
                \DB::table('akademik.hafalan_tahfidz')->delete();
                $this->line("✓ Hafalan Tahfidz deleted");
            }

            $after = \Modules\Kesantrian\Models\Santri::count();
            $this->line("Santri sesudah: {$after}");

            $this->info("\n✅ Cleanup complete!");
            return 0;
        } catch (\Throwable $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }
}
