<?php

namespace Modules\AI\Console;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\AI\Models\AiConversation;
use Modules\AI\Models\AiMessage;
use Modules\AI\Services\Settings\TenantSettingService;
use Throwable;

/**
 * T-A050 — ai:bersihkan-percakapan: retensi percakapan WA (Blueprint §5).
 *
 * Per tenant (pola loop AiEskalasiPengingatCommand):
 *  1. hapus ai_message lebih tua dari retensi_hari setting tenant aktif
 *     (default 90 hari bila Wizard belum disetel);
 *  2. hapus ai_conversation yang tak punya pesan tersisa DAN sudah tidak
 *     aktif melewati retensi — ikut memusnahkan nomor terenkripsi
 *     (minimalisasi data; percakapan opt_out DIPERTAHANKAN agar
 *     preferensi "jangan balas" tidak ikut terhapus).
 *
 * Selalu exit 0: kegagalan satu tenant di-log, tenant lain jalan terus.
 */
class AiBersihkanPercakapanCommand extends Command
{
    protected $signature = 'ai:bersihkan-percakapan
        {tenant? : ID tenant tertentu; kosong = SEMUA tenant}';

    protected $description = 'Hapus pesan percakapan WA AI yang melewati retensi_hari setting tenant (default 90 hari)';

    public function handle(): int
    {
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
                [$pesan, $percakapan, $hari] = $tenant->run(function (): array {
                    if (! Schema::hasTable('ai_message')) {
                        return [0, 0, '-']; // tenant lama belum termigrasi — lewati anggun.
                    }

                    $payload = app(TenantSettingService::class)->aktif()?->payload;
                    $hari = (int) ($payload['retensi_hari'] ?? 90);
                    if ($hari < 1) {
                        $hari = 90;
                    }

                    $batas = now()->subDays($hari);

                    $pesan = AiMessage::query()->where('created_at', '<', $batas)->delete();

                    $percakapan = AiConversation::query()
                        ->doesntHave('messages')
                        ->where('opt_out', false)
                        ->where(fn ($q) => $q->whereNull('terakhir_aktif')->orWhere('terakhir_aktif', '<', $batas))
                        ->where('created_at', '<', $batas)
                        ->delete();

                    return [$pesan, $percakapan, $hari];
                });

                $rows[] = [$tenant->getKey(), $hari, $pesan, $percakapan, 'OK'];
            } catch (Throwable $e) {
                Log::error('ai:bersihkan-percakapan gagal untuk tenant.', [
                    'tenant' => $tenant->getKey(),
                    'error' => $e->getMessage(),
                ]);
                $rows[] = [$tenant->getKey(), '-', '-', '-', 'GAGAL'];
            }
        }

        $this->table(['Tenant', 'Retensi (hari)', 'Pesan dihapus', 'Percakapan dihapus', 'Status'], $rows);
        $this->info('Pembersihan retensi percakapan WA selesai.');

        return self::SUCCESS;
    }
}
