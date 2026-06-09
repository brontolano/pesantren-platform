<?php

namespace Modules\Core\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Modules\Core\Models\AuditLog;

/**
 * F2 — pasang `use Auditable` pada model transaksional untuk jejak audit otomatis.
 * Modul consumer TIDAK menulis audit manual; cukup pakai trait ini.
 *
 * Field yang dikecualikan bisa di-override: protected array $auditExclude = [...].
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $m): void {
            $m->tulisAudit('create', [], $m->auditBersih($m->getAttributes()));
        });

        static::updated(function (Model $m): void {
            $after = $m->auditBersih($m->getChanges());
            if ($after === []) {
                return; // tak ada perubahan bermakna
            }
            $before = Arr::only($m->getOriginal(), array_keys($after));
            $m->tulisAudit('update', $before, $after);
        });

        static::deleted(function (Model $m): void {
            $m->tulisAudit('delete', $m->auditBersih($m->getOriginal()), []);
        });
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /** Catat baris audit (juga dipakai untuk aksi domain: approve/post/reverse). */
    public function tulisAudit(string $aksi, array $before = [], array $after = []): AuditLog
    {
        return AuditLog::create([
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'aksi' => $aksi,
            'actor_id' => auth()->id(),
            'perubahan' => ['before' => $before, 'after' => $after],
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    /** Buang field yang tak perlu diaudit. */
    private function auditBersih(array $attr): array
    {
        $exclude = array_merge(
            ['created_at', 'updated_at', 'remember_token', 'password'],
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        );

        return Arr::except($attr, $exclude);
    }
}
