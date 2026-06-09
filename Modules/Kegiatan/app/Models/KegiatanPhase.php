<?php

namespace Modules\Kegiatan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * G3-19 — Milestone/phase kegiatan (data Gantt timeline).
 */
class KegiatanPhase extends Model
{
    use BelongsToTenant;

    protected $table = 'kegiatan_phase';

    public const STATUS = ['rencana', 'berjalan', 'selesai'];

    protected $fillable = [
        'tenant_id', 'kegiatan_id', 'nama', 'urutan', 'tanggal_mulai', 'tanggal_selesai', 'status',
    ];

    protected $casts = [
        'urutan' => 'integer',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'kegiatan_id');
    }
}
