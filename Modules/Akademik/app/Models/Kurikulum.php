<?php

namespace Modules\Akademik\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WP-06 — Kurikulum per lembaga: daftar mapel per tingkat.
 */
class Kurikulum extends Model
{
    use BelongsToTenant;

    protected $table = 'kurikulum';

    protected $fillable = [
        'tenant_id',
        'lembaga_id',
        'nama',
        'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class, 'lembaga_id');
    }

    public function mapel(): HasMany
    {
        return $this->hasMany(KurikulumMapel::class, 'kurikulum_id')->orderBy('tingkat')->orderBy('urutan');
    }
}
