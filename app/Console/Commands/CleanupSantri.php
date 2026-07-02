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

            // Helper: check if schema.table exists
            $tableExists = fn (string $schema, string $table): bool => (bool) \DB::selectOne(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
                [$schema, $table]
            );

            // Delete related data — skip tables yang belum ada di tenant ini
            if ($tableExists('keuangan', 'pembayaran')) {
                \DB::table('keuangan.pembayaran')->delete();
                $this->line("✓ Pembayaran deleted");
            } else {
                $this->line("- Pembayaran table not found (skipped)");
            }

            if ($tableExists('akademik', 'nilai')) {
                \DB::table('akademik.nilai')->delete();
                $this->line("✓ Nilai deleted");
            } else {
                $this->line("- Nilai table not found (skipped)");
            }

            if ($tableExists('akademik', 'presensi')) {
                \DB::table('akademik.presensi')->delete();
                $this->line("✓ Presensi deleted");
            }

            if ($tableExists('keuangan', 'tabungan')) {
                \DB::table('keuangan.tabungan')->delete();
                $this->line("✓ Tabungan deleted");
            } else {
                $this->line("- Tabungan table not found (skipped)");
            }

            if ($tableExists('akademik', 'hafalan_tahfidz')) {
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
