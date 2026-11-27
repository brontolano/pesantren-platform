<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\Auth;

final class PlatformAudit
{
    public function record(string $event, ?int $organizationId = null, array $metadata = [], ?string $subjectType = null, int|string|null $subjectId = null, ?\App\Models\PlatformOperator $operator = null): AuditEvent
    {
        $actor = $operator ?? Auth::guard('platform')->user();

        return AuditEvent::query()->create([
            'organization_id' => $organizationId,
            'actor_type' => $actor ? 'platform_operator' : 'system',
            'actor_id' => $actor?->getAuthIdentifier(),
            'event' => $event,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId === null ? null : (string) $subjectId,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
