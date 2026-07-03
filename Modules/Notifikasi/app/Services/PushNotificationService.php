<?php

namespace Modules\Notifikasi\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * F6 — Web Push (VAPID). Kanal SUPLEMEN, bukan pengganti WA: dipanggil oleh
 * NotifDispatcher HANYA bila user punya subscription aktif (skip diam-diam
 * bila tidak — tanpa log noise). Subscription yang gagal permanen (404/410
 * "gone") otomatis dihapus.
 */
class PushNotificationService
{
    public function __construct(
        private readonly LogNotifikasiService $logService,
    ) {}

    /** True bila user punya minimal 1 subscription push aktif. */
    public function hasSubscription(User $user): bool
    {
        return PushSubscription::query()->where('user_id', $user->id)->exists();
    }

    public function send(User $user, string $title, string $body, ?string $url = null): void
    {
        $subscriptions = PushSubscription::query()->where('user_id', $user->id)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $publicKey = (string) config('services.webpush.public_key');
        $privateKey = (string) config('services.webpush.private_key');
        $subject = (string) config('services.webpush.subject');

        if ($publicKey === '' || $privateKey === '') {
            // Belum dikonfigurasi di VPS: jangan gagal berisik, cukup lewati.
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url ?? '/',
        ], JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->p256dh,
                    'authToken' => $sub->auth,
                ]),
                $payload,
            );
        }

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();

            try {
                if ($report->isSuccess()) {
                    $this->logService->create([
                        'jenis' => 'direct',
                        'channel' => 'push',
                        'target' => $user->email ?? (string) $user->id,
                        'status' => 'sent',
                        'payload' => ['title' => $title, 'body' => $body],
                    ]);

                    continue;
                }

                $statusCode = $report->getResponse()?->getStatusCode();

                if (in_array($statusCode, [404, 410], true)) {
                    PushSubscription::query()
                        ->where('user_id', $user->id)
                        ->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))
                        ->delete();
                }

                $this->logService->create([
                    'jenis' => 'direct',
                    'channel' => 'push',
                    'target' => $user->email ?? (string) $user->id,
                    'status' => 'failed',
                    'error_message' => (string) $report->getReason(),
                    'payload' => ['title' => $title, 'body' => $body],
                ]);
            } catch (Throwable $e) {
                Log::warning('PushNotificationService: gagal proses laporan kirim', ['error' => $e->getMessage()]);
            }
        }
    }
}
