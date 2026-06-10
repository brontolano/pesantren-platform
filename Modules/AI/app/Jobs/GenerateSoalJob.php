<?php

namespace Modules\AI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\AI\Models\AiDrafSoal;
use Modules\AI\Services\Generator\SoalGenerator;
use Modules\Notifikasi\Models\LogNotifikasi;
use Throwable;

/**
 * T-A040 — job Generator Soal di queue 'ai' (worker-ai, koneksi redis-ai;
 * Blueprint §7.3 async-by-default) — pola IngestDocumentJob.
 *
 * Idempotensi dua lapis:
 *  1. ShouldBeUnique per draf — dispatch ganda tertelan dedup selama jendela;
 *  2. SoalGenerator::proses — draf final di-skip; butir ditulis ulang utuh
 *     (bukan append); kunci metering per batch deterministik → retry tidak
 *     menggandakan butir maupun kredit.
 *
 * timeout 540 < retry_after 600 (jangan di-redeliver saat masih berjalan).
 */
class GenerateSoalJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 540;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $drafId,
        public readonly ?string $tenantId,
    ) {
        $this->onConnection('redis-ai');
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return sprintf('ai-soal:%s:%d', $this->tenantId ?? 'central', $this->drafId);
    }

    public function handle(SoalGenerator $generator): void
    {
        // QueueTenancyBootstrapper aktif, tapi inisialisasi eksplisit tetap
        // dipasang (pola IngestDocumentJob) — tidak boleh salah DB tenant.
        $initDiSini = false;

        if ($this->tenantId !== null && (! tenancy()->initialized
            || (string) tenant()?->getTenantKey() !== $this->tenantId)) {
            tenancy()->initialize($this->tenantId);
            $initDiSini = true;
        }

        try {
            $draf = $generator->proses($this->drafId);

            // Kegagalan BISNIS sudah ditangani anggun di service (status gagal
            // + pesan santun) — di sini cukup notifikasi sesuai hasil akhir.
            if ($draf->status === AiDrafSoal::STATUS_GAGAL) {
                $this->catatNotifikasi(
                    'failed',
                    'Penyusunan draf soal belum berhasil',
                    sprintf('Paket "%s" gagal disusun: %s', $draf->judul, (string) $draf->error_ringkas),
                    $draf,
                );

                return;
            }

            $this->catatNotifikasi(
                'sent',
                'Draf soal siap ditinjau',
                sprintf(
                    'Paket "%s" selesai disusun: %d butir menunggu tinjauan dan pengesahan Anda di halaman Generator Soal.',
                    $draf->judul,
                    count((array) $draf->butir),
                ),
                $draf,
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
            $draf = AiDrafSoal::query()->find($this->drafId);

            $draf?->update([
                'status' => AiDrafSoal::STATUS_GAGAL,
                'error_ringkas' => mb_substr($e->getMessage(), 0, 500),
            ]);

            $this->catatNotifikasi(
                'failed',
                'Penyusunan draf soal gagal',
                sprintf(
                    'Mohon maaf, paket "%s" gagal disusun: %s',
                    $draf?->judul ?? ('#'.$this->drafId),
                    mb_substr($e->getMessage(), 0, 300),
                ),
                $draf,
            );
        } catch (Throwable $inner) {
            Log::error('GenerateSoalJob: gagal menandai status gagal.', [
                'draf_id' => $this->drafId,
                'message' => $inner->getMessage(),
            ]);
        }
    }

    /** Notifikasi database (pola LogNotifikasi IngestDocumentJob) — best-effort. */
    private function catatNotifikasi(string $status, string $judul, string $pesan, ?AiDrafSoal $draf): void
    {
        try {
            LogNotifikasi::query()->create([
                'tenant_id' => $this->tenantId,
                'jenis' => 'ai_generator_soal',
                'channel' => 'database',
                'target' => $draf?->dibuat_oleh !== null ? 'user:'.$draf->dibuat_oleh : 'admin_tenant',
                'status' => $status,
                'payload' => [
                    'judul' => $judul,
                    'pesan' => $pesan,
                    'draf_id' => $this->drafId,
                ],
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('GenerateSoalJob: gagal menulis notifikasi.', [
                'draf_id' => $this->drafId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
