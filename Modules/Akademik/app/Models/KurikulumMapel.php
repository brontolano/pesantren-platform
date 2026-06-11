<?php

namespace Modules\Akademik\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WP-06 — Baris mapel kurikulum per tingkat (tingkat = konvensi kelas.tingkat).
 */
class KurikulumMapel extends Model
{
    use BelongsToTenant;

    protected $table = 'kurikulum_mapel';

    protected $fillable = [
        'tenant_id',
        'kurikulum_id',
        'pelajaran_id',
        'tingkat',
        'kitab_id',
        'jam_per_minggu',
        'is_wajib',
        'urutan',
    ];

    protected $casts = [
        'jam_per_minggu' => 'integer',
        'is_wajib' => 'boolean',
        'urutan' => 'integer',
    ];

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }

    public function pelajaran(): BelongsTo
    {
        return $this->belongsTo(Pelajaran::class, 'pelajaran_id');
    }

    public function kitab(): BelongsTo
    {
        return $this->belongsTo(Kitab::class, 'kitab_id');
    }
}
