<?php

namespace Modules\AI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\AI\Models\AiKnowledgeSource;
use Modules\AI\Services\Rag\IngestService;
use Modules\Notifikasi\Models\LogNotifikasi;
use Throwable;

/**
 * T-A030 — job ingest dokumen RAG di queue 'ai' (worker-ai, koneksi redis-ai;
 * Blueprint §4.3).
 *
 * Idempotensi dua lapis:
 *  1. ShouldBeUnique per (sourceId, versi_ingest) — dispatch ganda versi yang
 *     sama tertelan dedup; re-ingest menaikkan versi → kunci baru;
 *  2. prosesSumber menghapus chunk lama sumber sebelum menulis — retry/race
 *     tidak pernah menggandakan chunk.
 *
 * timeout 540 < retry_after 600 (jangan sampai job di-redeliver saat masih
 * berjalan). tries 2: kegagalan transien (provider embedding restart) layak 1 retry.
 */
class IngestDocumentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 540;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $sourceId,
        public readonly int $versiIngest,
        public readonly ?string $tenantId,
    ) {
        $this->onConnection('redis-ai');
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return sprintf('ai-ingest:%s:%d:v%d', $this->tenantId ?? 'central', $this->sourceId, $this->versiIngest);
    }

    public function handle(IngestService $service): void
    {
        // QueueTenancyBootstrapper aktif, tapi inisialisasi eksplisit tetap
        // dipasang (pola BroadcastWaJob) — job ini tak boleh salah DB tenant.
        $initDiSini = false;

        if ($this->tenantId !== null && (! tenancy()->initialized
            || (string) tenant()?->getTenantKey() !== $this->tenantId)) {
            tenancy()->initialize($this->tenantId);
            $initDiSini = true;
        }

        try {
            $source = $service->prosesSumber($this->sourceId);
            $this->catatNotifikasi(
                'sent',
                'Sumber pengetahuan siap digunakan',
                sprintf(
                    'Dokumen "%s" selesai diproses: %d potongan pengetahuan siap dirujuk Khadim Digital.',
                    $source->judul,
                    $source->jumlah_chunk,
                ),
            );
        } catch (Throwable $e) {
            if ($this->attempts() >= $this->tries) {
                $this->tandaiGagal($e);
            }

            throw $e; // biarkan queue melakukan retry sesuai tries
        } finally {
            if ($initDiSini && tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    /** Percobaan terakhir benar-benar habis (termasuk timeout) → status gagal. */
    public function failed(Throwable $e): void
    {
        $initDiSini = false;

        if ($this->tenantId !== null && ! tenancy()->initialized) {
            try {
                tenancy()->initialize($this->tenantId);
                $initDiSini = true;
            } catch (Throwable) {
                return; // tenant tiada — tidak ada yang bisa ditandai
            }
        }

        try {
            $this->tandaiGagal($e);
        } finally {
            if ($initDiSini && tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    private function tandaiGagal(Throwable $e): void
    {
        try {
            $source = AiKnowledgeSource::query()->find($this->sourceId);

            $source?->update([
                'status' => AiKnowledgeSource::STATUS_GAGAL,
                'error_ringkas' => mb_substr($e->getMessage(), 0, 500),
            ]);

            $this->catatNotifikasi(
                'failed',
                'Pemrosesan sumber pengetahuan gagal',
                sprintf(
                    'Mohon maaf, dokumen "%s" gagal diproses: %s',
                    $source?->judul ?? ('#'.$this->sourceId),
                    mb_substr($e->getMessage(), 0, 300),
                ),
            );
        } catch (Throwable $inner) {
            Log::error('IngestDocumentJob: gagal menandai status gagal.', [
                'source_id' => $this->sourceId,
                'message' => $inner->getMessage(),
            ]);
        }
    }

    /** Notifikasi database ke admin tenant (pola LogNotifikasi BudgetGuard) — best-effort. */
    private function catatNotifikasi(string $status, string $judul, string $pesan): void
    {
        try {
            LogNotifikasi::query()->create([
                'tenant_id' => $this->tenantId,
                'jenis' => 'ai_ingest',
                'channel' => 'database',
                'target' => 'admin_tenant',
                'status' => $status,
                'payload' => [
                    'judul' => $judul,
                    'pesan' => $pesan,
                    'source_id' => $this->sourceId,
                    'versi_ingest' => $this->versiIngest,
                ],
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('IngestDocumentJob: gagal menulis notifikasi ingest.', [
                'source_id' => $this->sourceId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
