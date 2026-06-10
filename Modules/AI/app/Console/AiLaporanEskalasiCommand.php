<?php

namespace Modules\AI\Console;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\AI\Services\Escalation\LaporanEskalasi;
use Modules\Notifikasi\Models\LogNotifikasi;
use Throwable;

/**
 * T-A023 — ai:laporan-eskalasi: laporan bulanan "Tanya Ustadz" untuk kyai
 * (PRD §7.6 — membangun kepercayaan bahwa pagar bekerja).
 *
 * Dijadwalkan tanggal 1 tiap bulan → laporan BULAN SEBELUMNYA per tenant
 * (loop pola RekonsiliasiHarianCommand), dikirim sebagai notifikasi database
 * ke pimpinan (target 'pimpinan' = role kepala / pemegang ai.setting.setujui).
 * Idempoten per (tenant, periode): jalan ulang tidak menggandakan laporan.
 * Detail agregat + contoh anonim juga tampil di halaman EskalasiTanyaUstadz.
 */
class AiLaporanEskalasiCommand extends Command
{
    protected $signature = 'ai:laporan-eskalasi
        {tenant? : ID tenant tertentu; kosong = SEMUA tenant}
        {--bulan= : Periode laporan (YYYY-MM); kosong = bulan lalu}';

    protected $description = 'Laporan bulanan eskalasi Tanya Ustadz (volume, kategori, waktu jawab, contoh anonim) ke pimpinan per tenant';

    public function handle(LaporanEskalasi $laporan): int
    {
        $bulan = $this->option('bulan')
            ? Carbon::createFromFormat('Y-m', (string) $this->option('bulan'))->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        $target = $this->argument('tenant');

        $tenants = $target
            ? Tenant::query()->where('id', $target)->get()
            : Tenant::query()->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->error($target ? "Tenant '{$target}' tidak ditemukan." : 'Tidak ada tenant.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($tenants as $tenant) {
            try {
                $status = $tenant->run(function () use ($laporan, $bulan): string {
                    if (! Schema::hasTable('ai_escalation') || ! Schema::hasTable('log_notifikasi')) {
                        return 'dilewati (tabel belum ada)';
                    }

                    $data = $laporan->bulanan($bulan);

                    // Idempoten per periode: laporan yang sama tidak dikirim dua kali.
                    $sudahAda = LogNotifikasi::query()
                        ->where('jenis', 'ai_laporan_eskalasi')
                        ->where('payload->periode', $data['periode'])
                        ->exists();

                    if ($sudahAda) {
                        return 'sudah terkirim';
                    }

                    LogNotifikasi::query()->create([
                        'jenis' => 'ai_laporan_eskalasi',
                        'channel' => 'database',
                        'target' => 'pimpinan',
                        'status' => 'sent',
                        'payload' => [
                            // 'periode' level atas = kunci dedup idempoten di atas.
                            'periode' => $data['periode'],
                            'judul' => 'Laporan bulanan Tanya Ustadz — '.$data['periode'],
                            'pesan' => $this->ringkasanPesan($data),
                            'laporan' => $data,
                        ],
                        'created_at' => now(),
                    ]);

                    return "terkirim ({$data['total']} eskalasi)";
                });

                $rows[] = [$tenant->getKey(), $status];
            } catch (Throwable $e) {
                Log::error('ai:laporan-eskalasi gagal untuk tenant.', [
                    'tenant' => $tenant->getKey(),
                    'error' => $e->getMessage(),
                ]);
                $rows[] = [$tenant->getKey(), 'GAGAL'];
            }
        }

        $this->table(['Tenant', 'Laporan '.$bulan->format('Y-m')], $rows);
        $this->info('Laporan bulanan eskalasi Tanya Ustadz selesai diproses.');

        return self::SUCCESS;
    }

    /** @param array{total:int, volume_per_kategori:array<string,int>, jumlah_dijawab:int, rata_rata_jawab_jam:float|null} $data */
    private function ringkasanPesan(array $data): string
    {
        $kategori = $data['volume_per_kategori'] === []
            ? '-'
            : collect($data['volume_per_kategori'])
                ->map(static fn (int $jumlah, string $nama): string => "{$nama}: {$jumlah}")
                ->implode(', ');

        $rataRata = $data['rata_rata_jawab_jam'] !== null
            ? number_format($data['rata_rata_jawab_jam'], 1, ',', '.').' jam'
            : '-';

        return sprintf(
            'Sepanjang periode ini terdapat %d eskalasi Tanya Ustadz (%s). '
            .'%d telah dijawab dengan rata-rata waktu jawab %s. '
            .'Rincian dan contoh anonim tersedia di halaman Eskalasi Tanya Ustadz.',
            $data['total'],
            $kategori,
            $data['jumlah_dijawab'],
            $rataRata,
        );
    }
}
