<?php

namespace Modules\Notifikasi\Services;

use Illuminate\Support\Facades\Mail;
use Modules\Notifikasi\Mail\GenericNotificationMail;
use Modules\Notifikasi\Models\TemplateNotifikasi;

class EmailNotificationService
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly LogNotifikasiService $logService,
    ) {
    }

    public function send(TemplateNotifikasi $template, string $email, array $variables = []): void
    {
        $subject = $this->renderer->render($template->judul, $variables);
        $body = $this->renderer->render($template->isi, $variables);

        Mail::to($email)->send(new GenericNotificationMail($subject, $body));

        $this->logService->forEmail($template, $email, [
            'subject' => $subject,
            'preview' => str($body)->limit(120)->toString(),
        ]);
    }
}
