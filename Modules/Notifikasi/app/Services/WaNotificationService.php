<?php

namespace Modules\Notifikasi\Services;

use Throwable;
use Modules\Notifikasi\Models\BroadcastWa;
use Modules\Notifikasi\Models\TemplateNotifikasi;

class WaNotificationService
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly WaGatewayService $gatewayService,
        private readonly LogNotifikasiService $logService,
    ) {
    }

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

            $this->logService->forSuccess($broadcast, $target, $response);

            return true;
        } catch (Throwable $throwable) {
            $this->logService->forFailure($broadcast, $target, $throwable->getMessage());

            return false;
        }
    }
}
