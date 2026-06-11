<?php

namespace Modules\Kesantrian\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WP-21 — Penugasan pengurus-santri per qism (domain tugas) + periode jabatan.
 * Status "berlaku" SELALU dihitung saat query (scopeBerlaku) — masa jabatan
 * habis otomatis menutup akses tanpa cron.
 */
class PengurusSantri extends Model
{
    use BelongsToTenant;

    protected $table = 'pengurus_santri';

    protected $fillable = [
        'tenant_id', 'santri_id', 'qism', 'label',
        'periode_mulai', 'periode_selesai', 'aktif',
    ];

    protected $casts = [
        'periode_mulai' => 'date',
        'periode_selesai' => 'date',
        'aktif' => 'boolean',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    /** Penugasan yang berlaku HARI INI: aktif + periode mencakup hari ini (selesai null = terbuka). */
    public function scopeBerlaku(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where('aktif', true)
            ->whereDate('periode_mulai', '<=', $today)
            ->where(fn (Builder $q) => $q
                ->whereNull('periode_selesai')
                ->orWhereDate('periode_selesai', '>=', $today));
    }
}
