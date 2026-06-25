<?php

namespace Modules\Notifikasi\Services;

use Modules\Notifikasi\Models\BroadcastWa;
use Modules\Notifikasi\Models\TemplateNotifikasi;
use Throwable;

class WaNotificationService
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly WaGatewayService $gatewayService,
        private readonly LogNotifikasiService $logService,
    ) {}

    public function sendToRecipient(
        BroadcastWa $broadcast,
        TemplateNotifikasi $template,
        string $target,
        array $variables = []
    ): bool {
        try {
            $subject = $this->renderer->render($template->judul, $variables);
            $message = $this->renderer->render($template->isi, $variables);

            $response = $this->gatewayService->send($target, $template, $message, [
                'subject' => $subject,
                'variables' => $variables,
            ]);

            // Gateway tak benar-benar mengirim — 'simulated' (WA belum dikonfigurasi)
            // atau 'disabled' (WA dimatikan). JANGAN catat 'sent': log jujur + hitung
            // sebagai TIDAK terkirim agar staf tak salah kira pesan sampai.
            $status = is_array($response) ? ($response['status'] ?? null) : null;
            if (in_array($status, ['simulated', 'disabled'], true)) {
                $this->logService->forUndelivered($broadcast, $target, $status, is_array($response) ? $response : []);

                return false;
            }

            $this->logService->forSuccess($broadcast, $target, $response);

            return true;
        } catch (Throwable $throwable) {
            $this->logService->forFailure($broadcast, $target, $throwable->getMessage());

            return false;
        }
    }
}
