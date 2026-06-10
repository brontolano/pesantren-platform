<?php

namespace Modules\AI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\AI\Models\AiDrafRpp;
use Modules\AI\Services\Generator\RppGenerator;
use Modules\Notifikasi\Models\LogNotifikasi;
use Throwable;

/**
 * T-A041 — job Generator RPP di queue 'ai' (worker-ai, koneksi redis-ai;
 * Blueprint §7.3 async-by-default) — pola GenerateSoalJob (T-A040).
 *
 * Idempotensi dua lapis:
 *  1. ShouldBeUnique per draf — dispatch ganda tertelan dedup selama jendela;
 *  2. RppGenerator::proses — draf final di-skip; isi ditulis ulang utuh
 *     (bukan append); kunci metering per seksi deterministik → retry tidak
 *     menggandakan isi maupun kredit.
 *
 * timeout 540 < retry_after 600 (jangan di-redeliver saat masih berjalan).
 */
class GenerateRppJob implements ShouldBeUnique, ShouldQueue
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
        return sprintf('ai-rpp:%s:%d', $this->tenantId ?? 'central', $this->drafId);
    }

    public function handle(RppGenerator $generator): void
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
            if ($draf->status === AiDrafRpp::STATUS_GAGAL) {
                $this->catatNotifikasi(
                    'failed',
                    'Penyusunan draf RPP belum berhasil',
                    sprintf('Dokumen "%s" gagal disusun: %s', $draf->judul, (string) $draf->error_ringkas),
                    $draf,
                );

                return;
            }

            $this->catatNotifikasi(
                'sent',
                'Draf RPP siap ditinjau',
                sprintf(
                    'Dokumen "%s" selesai disusun: %d seksi menunggu tinjauan dan pengesahan Anda di halaman Generator RPP.',
                    $draf->judul,
                    count((array) $draf->isi),
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
            $draf = AiDrafRpp::query()->find($this->drafId);

            $draf?->update([
                'status' => AiDrafRpp::STATUS_GAGAL,
                'error_ringkas' => mb_substr($e->getMessage(), 0, 500),
            ]);

            $this->catatNotifikasi(
                'failed',
                'Penyusunan draf RPP gagal',
                sprintf(
                    'Mohon maaf, dokumen "%s" gagal disusun: %s',
                    $draf?->judul ?? ('#'.$this->drafId),
                    mb_substr($e->getMessage(), 0, 300),
                ),
                $draf,
            );
        } catch (Throwable $inner) {
            Log::error('GenerateRppJob: gagal menandai status gagal.', [
                'draf_id' => $this->drafId,
                'message' => $inner->getMessage(),
            ]);
        }
    }

    /** Notifikasi database (pola LogNotifikasi GenerateSoalJob) — best-effort. */
    private function catatNotifikasi(string $status, string $judul, string $pesan, ?AiDrafRpp $draf): void
    {
        try {
            LogNotifikasi::query()->create([
                'tenant_id' => $this->tenantId,
                'jenis' => 'ai_generator_rpp',
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
            Log::error('GenerateRppJob: gagal menulis notifikasi.', [
                'draf_id' => $this->drafId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
