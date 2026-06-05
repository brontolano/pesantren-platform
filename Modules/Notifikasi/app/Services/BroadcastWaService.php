<?php

namespace Modules\Notifikasi\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Notifikasi\Jobs\BroadcastWaJob;
use Modules\Notifikasi\Models\BroadcastWa;

class BroadcastWaService
{
    public function __construct(
        private readonly RecipientResolver $recipientResolver,
    ) {
    }

    public function create(array $attributes, ?User $creator = null): BroadcastWa
    {
        return BroadcastWa::create([
            'template_id' => $attributes['template_id'],
            'target_group' => $attributes['target_group'],
            'filter_criteria' => $attributes['filter_criteria'] ?? [],
            'status' => $attributes['status'] ?? 'draft',
            'tanggal_kirim' => $attributes['tanggal_kirim'] ?? null,
            'created_by' => $creator?->id,
        ]);
    }

    public function queue(BroadcastWa $broadcast): BroadcastWa
    {
        $broadcast->update([
            'status' => 'queued',
            'tanggal_kirim' => now(),
        ]);

        BroadcastWaJob::dispatch($broadcast->id, tenant()?->getTenantKey());

        return $broadcast->fresh();
    }

    public function execute(BroadcastWa $broadcast): array
    {
        $broadcast->loadMissing('template');

        $recipients = $this->recipientResolver->resolve(
            $broadcast->target_group,
            $broadcast->filter_criteria ?? []
        );

        $result = [
            'total' => $recipients->count(),
            'sent' => 0,
            'failed' => 0,
        ];

        DB::transaction(function () use ($broadcast): void {
            $broadcast->increment('attempt_count');
            $broadcast->update(['status' => 'processing']);
        });

        $sender = app(WaNotificationService::class);
        $interval = max(0, (int) config('notifikasi.broadcast_interval_seconds', 10));
        $recipientList = $recipients->values();
        $lastIndex = $recipientList->count() - 1;

        foreach ($recipientList as $i => $recipient) {
            $sent = $sender->sendToRecipient(
                $broadcast,
                $broadcast->template,
                $recipient['target'],
                $recipient['variables']
            );

            if ($sent) {
                $result['sent']++;
            } else {
                $result['failed']++;
            }

            // Throttle: jeda antar-pesan (default 10 dtk) agar tak kena rate-limit/blokir WhatsApp.
            // Job ini berjalan di queue 'broadcast' (worker terpisah) supaya tidak menahan OTP.
            if ($interval > 0 && $i < $lastIndex) {
                sleep($interval);
            }
        }

        $broadcast->update([
            'status' => $result['failed'] > 0 ? 'partial_failed' : 'sent',
            'last_error_message' => $result['failed'] > 0 ? 'Sebagian target gagal dikirim.' : null,
        ]);

        return $result;
    }

    public function retryFailed(BroadcastWa $broadcast): BroadcastWa
    {
        $broadcast->update([
            'status' => 'queued',
            'last_error_message' => null,
        ]);

        BroadcastWaJob::dispatch($broadcast->id, tenant()?->getTenantKey());

        return $broadcast->fresh();
    }
}
