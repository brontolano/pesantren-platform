<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Ledger\Services\LedgerReconciliationService;
use Throwable;

/**
 * Rekonsiliasi Ledger lintas-tenant (Blueprint Arsitektur §16).
 *
 * Deteksi transaksi sumber terverifikasi TANPA jurnal pasangan (drift akibat
 * listener posting yang sengaja fail-silent) → ALARM via Log::warning. Dengan
 * --repost, sembuhkan dengan memancarkan ulang event (listener idempoten).
 * Default = DETEKSI saja → aman dijadwalkan harian; penyembuhan tetap keputusan
 * sadar operator. Selalu exit 0 agar penjadwal tak gagal (alarm yang menandai).
 *
 * Dijadwalkan harian di routes/console.php.
 */
class RekonsiliasiLedgerCommand extends Command
{
    protected $signature = 'ledger:rekonsiliasi
        {tenant? : ID tenant tertentu; kosong = SEMUA tenant}
        {--repost : Pancarkan ulang event untuk transaksi tanpa jurnal (penyembuhan idempoten)}';

    protected $description = 'Rekonsiliasi Ledger: deteksi (±re-post) transaksi terverifikasi tanpa jurnal per tenant (§16)';

    public function handle(): int
    {
        $repost = (bool) $this->option('repost');
        $target = $this->argument('tenant');

        $tenants = $target
            ? Tenant::query()->where('id', $target)->get()
            : Tenant::query()->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->error($target ? "Tenant '{$target}' tidak ditemukan." : 'Tidak ada tenant.');

            return self::FAILURE;
        }

        $rows = [];
        $totalHilang = 0;
        $totalRepost = 0;

        foreach ($tenants as $tenant) {
            try {
                $r = $tenant->run(fn (): array => app(LedgerReconciliationService::class)->auditJurnalHilang($repost));

                if (isset($r['skip'])) {
                    $rows[] = [$tenant->getKey(), '-', '-', 'SKIP: '.$r['skip']];

                    continue;
                }

                $hilang = $r['pembayaran_hilang'] + $r['tabungan_hilang'];
                $totalHilang += $hilang;
                $totalRepost += $r['reposted'];

                if ($hilang > 0) {
                    Log::warning('Rekonsiliasi Ledger: transaksi terverifikasi tanpa jurnal.', [
                        'tenant' => $tenant->getKey(),
                        'pembayaran_hilang' => $r['pembayaran_hilang'],
                        'tabungan_hilang' => $r['tabungan_hilang'],
                        'reposted' => $r['reposted'],
                        'butuh_manual' => $r['butuh_manual'],
                        'contoh' => $r['contoh'],
                    ]);
                }

                $status = $hilang === 0
                    ? 'OK'
                    : ($repost ? "⚠ repost {$r['reposted']} / manual {$r['butuh_manual']}" : '⚠ DRIFT');
                $rows[] = [$tenant->getKey(), $r['pembayaran_hilang'], $r['tabungan_hilang'], $status];
            } catch (Throwable $e) {
                Log::error('Rekonsiliasi Ledger gagal untuk tenant.', [
                    'tenant' => $tenant->getKey(),
                    'error' => $e->getMessage(),
                ]);
                $rows[] = [$tenant->getKey(), '-', '-', 'GAGAL'];
            }
        }

        $this->table(['Tenant', 'Pembayaran∅', 'Tabungan∅', 'Status'], $rows);

        if ($totalHilang > 0) {
            $this->warn("⚠ Total {$totalHilang} transaksi tanpa jurnal"
                .($repost ? " ({$totalRepost} di-repost) — cek log." : ' — jalankan --repost untuk menyembuhkan.'));
        } else {
            $this->info('Ledger sinkron: semua transaksi terverifikasi punya jurnal pasangan.');
        }

        return self::SUCCESS;
    }
}
