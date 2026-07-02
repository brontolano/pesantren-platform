<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Notifikasi\Services\WahaHealthService;
use Throwable;

/**
 * Health-check sesi WAHA (WhatsApp HTTP API) lintas-tenant.
 *
 * Memeriksa apakah sesi WAHA (default) berstatus 'WORKING'. Bila TIDAK
 * (SCAN_QR_CODE/STOPPED/FAILED/tak terjangkau) → ALARM via Log::warning
 * (jalur notifikasi internal yang sudah ada, tak membuat sistem alert baru)
 * agar operator tahu WA "diam-diam mati" (nomor logout / QR perlu di-scan ulang).
 *
 * Probe DITELAN (fail-soft): kegagalan health-check tak pernah throw & command
 * selalu exit 0 → aman dijadwalkan harian tanpa menggagalkan penjadwal, dan
 * tak pernah mengganggu jalur kirim notifikasi.
 *
 * Dijadwalkan harian di routes/console.php (bareng jendela 06:30).
 */
class WaHealthCheckCommand extends Command
{
    protected $signature = 'wa:health-check
        {tenant? : ID tenant tertentu; kosong = SEMUA tenant}
        {--timeout=5 : Batas waktu probe HTTP (detik)}';

    protected $description = 'Health-check sesi WAHA per tenant: alarm bila sesi ≠ WORKING (fail-soft)';

    public function handle(): int
    {
        $target = $this->argument('tenant');
        $timeout = max(1, (int) $this->option('timeout'));

        $tenants = $target
            ? Tenant::query()->where('id', $target)->get()
            : Tenant::query()->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->error($target ? "Tenant '{$target}' tidak ditemukan." : 'Tidak ada tenant.');

            return self::SUCCESS; // tetap 0 — ketiadaan tenant bukan kegagalan probe
        }

        $rows = [];
        $bermasalah = 0;

        foreach ($tenants as $tenant) {
            try {
                $r = $tenant->run(fn (): array => app(WahaHealthService::class)->check($timeout));
            } catch (Throwable $e) {
                // Konteks tenant gagal di-boot → catat, lanjut. Jangan throw.
                Log::warning('WA health-check: gagal memproses tenant.', [
                    'tenant' => $tenant->getKey(),
                    'error' => $e->getMessage(),
                ]);
                $rows[] = [$tenant->getKey(), '-', 'GAGAL BOOT'];

                continue;
            }

            // WAHA langsung tak dikonfigurasi (pakai pipa n8n) → lewati diam-diam,
            // tak ada yang bisa diperiksa dari sisi ini.
            if (! ($r['configured'] ?? false)) {
                $rows[] = [$tenant->getKey(), '-', 'SKIP (n8n)'];

                continue;
            }

            if ($r['healthy'] ?? false) {
                $rows[] = [$tenant->getKey(), $r['status'], 'OK'];

                continue;
            }

            $bermasalah++;
            Log::warning('WA health-check: sesi WAHA TIDAK sehat.', [
                'tenant' => $tenant->getKey(),
                'session' => $r['session'] ?? null,
                'status' => $r['status'] ?? null,
                'error' => $r['error'] ?? null,
            ]);
            $rows[] = [$tenant->getKey(), $r['status'] ?? '—', '⚠ '.($r['error'] ?? 'tidak sehat')];
        }

        $this->table(['Tenant', 'Status Sesi', 'Hasil'], $rows);

        if ($bermasalah > 0) {
            $this->warn("⚠ {$bermasalah} tenant dengan sesi WAHA bermasalah — cek log (WA diam-diam mati?).");
        } else {
            $this->info('Semua sesi WAHA yang terkonfigurasi sehat (WORKING).');
        }

        return self::SUCCESS;
    }
}
