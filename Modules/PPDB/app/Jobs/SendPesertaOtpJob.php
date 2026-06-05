<?php

namespace Modules\PPDB\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Notifikasi\Models\TemplateNotifikasi;
use Modules\Notifikasi\Services\TemplateRenderer;
use Modules\Notifikasi\Services\WaGatewayService;
use Modules\PPDB\Models\UserOtp;

/**
 * Kirim OTP peserta via WA — antrean (queue `notifikasi`, Redis di produksi).
 *
 * Komunikasi antar-modul HANYA lewat service Modules/Notifikasi
 * (TemplateRenderer + WaGatewayService) sesuai Blueprint §4 — tanpa query
 * silang ke tabel modul lain selain model template yang memang dipublikasikan.
 *
 * Template `ppdb_otp` per tenant (configuration over customization §14):
 * bila tenant belum punya, dibuat default — panitia bebas mengubah isi
 * lewat panel Notifikasi tanpa menyentuh kode.
 *
 * KEAMANAN: kode OTP TIDAK PERNAH di-log. Payload job berisi plaintext kode
 * sesaat — pastikan koneksi queue produksi (Redis) tidak terekspos.
 */
class SendPesertaOtpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const TEMPLATE_CODE = 'ppdb_otp';

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    /**
     * @param  array<string, string>  $variables  variabel template tambahan (mis. nama)
     */
    public function __construct(
        public readonly ?string $tenantId,
        public readonly string $targetPhone,
        public readonly string $plainCode,
        public readonly string $purpose,
        public readonly array $variables = [],
    ) {
        $this->onQueue('notifikasi');
    }

    public function handle(TemplateRenderer $renderer, WaGatewayService $gateway): void
    {
        if ($this->tenantId) {
            tenancy()->initialize($this->tenantId);
        }

        try {
            $template = $this->resolveTemplate();

            $variables = array_merge($this->variables, [
                'kode_otp' => $this->plainCode,
                'berlaku_menit' => (string) UserOtp::TTL_MINUTES,
                'tujuan' => $this->purpose === UserOtp::PURPOSE_RESET
                    ? 'mengatur ulang password'
                    : 'mengaktifkan akun',
                'nama_pesantren' => tenant()?->getAttribute('name') ?? 'Pesantren',
            ]);

            $message = $renderer->render($template->isi, $variables);

            $gateway->send($this->targetPhone, $template, $message, [
                'subject' => $renderer->render($template->judul, $variables),
            ]);
        } catch (\Throwable $e) {
            // Hanya metadata — JANGAN log kode OTP / isi pesan.
            Log::warning('ppdb_otp.send_failed', [
                'tenant_id' => $this->tenantId,
                'purpose' => $this->purpose,
                'error' => $e->getMessage(),
            ]);

            throw $e; // biarkan queue retry sesuai backoff
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    /**
     * Ambil template `ppdb_otp` tenant; buat default bila belum ada.
     */
    private function resolveTemplate(): TemplateNotifikasi
    {
        return TemplateNotifikasi::query()->firstOrCreate(
            ['kode_template' => self::TEMPLATE_CODE],
            [
                'tenant_id' => tenant()?->getTenantKey(),
                'judul' => 'Kode Verifikasi Pendaftaran',
                'channel' => 'whatsapp',
                'is_active' => true,
                'variable_dinamis' => ['nama', 'kode_otp', 'berlaku_menit', 'tujuan', 'nama_pesantren'],
                'isi' => <<<'MSG'
Assalamu'alaikum {{nama}},

Kode verifikasi Anda untuk {{tujuan}} di Portal SPMB {{nama_pesantren}}:

🔐 *{{kode_otp}}*

Kode berlaku {{berlaku_menit}} menit. JANGAN berikan kode ini kepada siapa pun, termasuk yang mengaku panitia.

— Panitia SPMB {{nama_pesantren}}
MSG,
            ],
        );
    }
}
