<?php

namespace Modules\AI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\AI\Models\AiDrafIkhtisar;
use Modules\AI\Services\Generator\IkhtisarGenerator;
use Modules\Notifikasi\Models\LogNotifikasi;
use Throwable;

/**
 * T-A042 — job Ikhtisar Kitab / Kerangka Kultum di queue 'ai' (worker-ai,
 * koneksi redis-ai; Blueprint §7.3 async-by-default) — pola GenerateRppJob.
 *
 * Idempotensi dua lapis:
 *  1. ShouldBeUnique per draf — dispatch ganda tertelan dedup selama jendela;
 *  2. IkhtisarGenerator::proses — draf final di-skip; isi ditulis ulang utuh
 *     (bukan append); kunci metering deterministik → retry tidak menggandakan
 *     isi maupun kredit.
 *
 * timeout 540 < retry_after 600 (jangan di-redeliver saat masih berjalan).
 */
class GenerateIkhtisarJob implements ShouldBeUnique, ShouldQueue
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
        return sprintf('ai-ikhtisar:%s:%d', $this->tenantId ?? 'central', $this->drafId);
    }

    public function handle(IkhtisarGenerator $generator): void
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
            if ($draf->status === AiDrafIkhtisar::STATUS_GAGAL) {
                $this->catatNotifikasi(
                    'failed',
                    'Penyusunan draf belum berhasil',
                    sprintf('Dokumen "%s" gagal disusun: %s', $draf->judul, (string) $draf->error_ringkas),
                    $draf,
                );

                return;
            }

            $this->catatNotifikasi(
                'sent',
                'Draf siap ditinjau',
                sprintf(
                    'Dokumen "%s" selesai disusun dan menunggu tinjauan serta pengesahan Anda di halaman Ikhtisar Kitab.',
                    $draf->judul,
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
            $draf = AiDrafIkhtisar::query()->find($this->drafId);

            $draf?->update([
                'status' => AiDrafIkhtisar::STATUS_GAGAL,
                'error_ringkas' => mb_substr($e->getMessage(), 0, 500),
            ]);

            $this->catatNotifikasi(
                'failed',
                'Penyusunan draf gagal',
                sprintf(
                    'Mohon maaf, dokumen "%s" gagal disusun: %s',
                    $draf?->judul ?? ('#'.$this->drafId),
                    mb_substr($e->getMessage(), 0, 300),
                ),
                $draf,
            );
        } catch (Throwable $inner) {
            Log::error('GenerateIkhtisarJob: gagal menandai status gagal.', [
                'draf_id' => $this->drafId,
                'message' => $inner->getMessage(),
            ]);
        }
    }

    /** Notifikasi database (pola LogNotifikasi GenerateRppJob) — best-effort. */
    private function catatNotifikasi(string $status, string $judul, string $pesan, ?AiDrafIkhtisar $draf): void
    {
        try {
            LogNotifikasi::query()->create([
                'tenant_id' => $this->tenantId,
                'jenis' => 'ai_generator_ikhtisar',
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
            Log::error('GenerateIkhtisarJob: gagal menulis notifikasi.', [
                'draf_id' => $this->drafId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
