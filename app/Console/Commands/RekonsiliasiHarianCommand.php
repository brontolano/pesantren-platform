<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Keuangan\Services\RekonsiliasiService;
use Throwable;

/**
 * Rekonsiliasi harian lintas-tenant (invarian I-7).
 *
 * Untuk tiap tenant: deteksi anomali status tagihan (status drift) lewat
 * RekonsiliasiService::deteksiAnomaliStatus(). Bila ada selisih → ALARM via
 * Log::warning (channel default; bisa diteruskan ke notifikasi). Selalu selesai
 * (exit 0) agar penjadwal tak gagal; alarm-nya yang menandai masalah.
 *
 * Dijadwalkan harian di routes/console.php.
 */
class RekonsiliasiHarianCommand extends Command
{
    protected $signature = 'keuangan:rekonsiliasi-harian
        {tenant? : ID tenant tertentu; kosong = SEMUA tenant}';

    protected $description = 'Rekonsiliasi harian: deteksi & alarmkan anomali status tagihan per tenant (I-7)';

    public function handle(): int
    {
        $target = $this->argument('tenant');

        $tenants = $target
            ? Tenant::query()->where('id', $target)->get()
            : Tenant::query()->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->error($target ? "Tenant '{$target}' tidak ditemukan." : 'Tidak ada tenant.');

            return self::FAILURE;
        }

        $rows = [];
        $totalAnomali = 0;

        foreach ($tenants as $tenant) {
            try {
                $anomali = $tenant->run(fn (): array => (new RekonsiliasiService())->deteksiAnomaliStatus());
                $jumlah = count($anomali);
                $totalAnomali += $jumlah;

                if ($jumlah > 0) {
                    Log::warning('Rekonsiliasi harian: anomali status tagihan terdeteksi.', [
                        'tenant' => $tenant->getKey(),
                        'jumlah_anomali' => $jumlah,
                        'contoh' => array_slice($anomali, 0, 5),
                    ]);
                }

                $rows[] = [$tenant->getKey(), $jumlah, $jumlah > 0 ? '⚠ ANOMALI' : 'OK'];
            } catch (Throwable $e) {
                Log::error('Rekonsiliasi harian gagal untuk tenant.', [
                    'tenant' => $tenant->getKey(),
                    'error' => $e->getMessage(),
                ]);
                $rows[] = [$tenant->getKey(), '-', 'GAGAL'];
            }
        }

        $this->table(['Tenant', 'Anomali', 'Status'], $rows);

        if ($totalAnomali > 0) {
            $this->warn("⚠ Total {$totalAnomali} anomali status terdeteksi — cek log channel default.");
        } else {
            $this->info('Rekonsiliasi bersih: nol anomali status di semua tenant.');
        }

        return self::SUCCESS;
    }
}
