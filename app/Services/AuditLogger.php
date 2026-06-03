<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;

class AuditLogger
{
    public function log(
        string $action,
        ?Authenticatable $user = null,
        ?object $subject = null,
        array $metadata = [],
    ): AuditLog {
        return AuditLog::create([
            'tenant_id' => data_get($user, 'tenant_id', tenant('id')),
            'user_id' => $user?->getAuthIdentifier(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => ($subject && method_exists($subject, 'getKey')) ? $subject->getKey() : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
