<?php

namespace Modules\Keuangan\Console;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Keuangan\Models\TagihanRutin;
use Modules\Keuangan\Services\TagihanRutinService;
use Throwable;

/**
 * Penerbitan Tagihan Rutin lintas-tenant.
 *
 * Dijadwalkan HARIAN (routes/console.php). Untuk tiap tenant, terbitkan aturan
 * rutin AKTIF yang `tanggal_terbit`-nya == hari ini (kecuali --semua memaksa
 * semua aturan aktif). Idempoten berlapis di TagihanRutinService → aman walau
 * jalan tiap hari atau di-trigger ulang.
 */
class TerbitkanTagihanRutinCommand extends Command
{
    protected $signature = 'keuangan:terbitkan-tagihan-rutin
        {tenant? : ID tenant tertentu; kosong = SEMUA tenant}
        {--periode= : Periode Y-m (default: bulan berjalan)}
        {--semua : Proses semua aturan aktif, abaikan kecocokan tanggal terbit}
        {--force : Terbitkan ulang walau aturan sudah ditandai terbit periode ini}';

    protected $description = 'Terbitkan Tagihan Rutin (SPP/makan/asrama dll) otomatis per tenant — idempoten';

    public function handle(): int
    {
        $target = $this->argument('tenant');
        $periode = $this->option('periode') ?: now()->format('Y-m');
        $semua = (bool) $this->option('semua');
        $force = (bool) $this->option('force');
        $hariIni = now()->day;

        $tenants = $target
            ? Tenant::query()->where('id', $target)->get()
            : Tenant::query()->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->error($target ? "Tenant '{$target}' tidak ditemukan." : 'Tidak ada tenant.');

            return self::FAILURE;
        }

        $rows = [];
        $totalDibuat = 0;

        foreach ($tenants as $tenant) {
            try {
                $hasil = $tenant->run(function () use ($periode, $semua, $force, $hariIni): array {
                    $service = new TagihanRutinService;

                    $aturanList = TagihanRutin::query()
                        ->where('aktif', true)
                        ->when(! $semua, fn ($q) => $q->where('tanggal_terbit', $hariIni))
                        ->get();

                    $dibuat = 0;
                    $diproses = 0;
                    foreach ($aturanList as $aturan) {
                        $s = $service->terbitkan($aturan, $periode, $force);
                        $dibuat += $s['dibuat'];
                        $diproses++;
                    }

                    return ['aturan' => $diproses, 'dibuat' => $dibuat];
                });

                $totalDibuat += $hasil['dibuat'];
                if ($hasil['aturan'] > 0) {
                    $rows[] = [$tenant->getKey(), $hasil['aturan'], $hasil['dibuat']];
                }

                if ($hasil['dibuat'] > 0) {
                    Log::info('Tagihan rutin diterbitkan.', [
                        'tenant' => $tenant->getKey(),
                        'periode' => $periode,
                        'tagihan_dibuat' => $hasil['dibuat'],
                    ]);
                }
            } catch (Throwable $e) {
                Log::error('Penerbitan tagihan rutin gagal untuk tenant.', [
                    'tenant' => $tenant->getKey(),
                    'error' => $e->getMessage(),
                ]);
                $rows[] = [$tenant->getKey(), '-', 'GAGAL'];
            }
        }

        if ($rows !== []) {
            $this->table(['Tenant', 'Aturan diproses', 'Tagihan dibuat'], $rows);
        }
        $this->info("Selesai. Total tagihan dibuat: {$totalDibuat} (periode {$periode}).");

        return self::SUCCESS;
    }
}
