<?php

namespace Modules\Kegiatan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

/**
 * G3-19 — Jadwal berulang kegiatan. Anchor `mulai`, langkah `interval`
 * minggu/bulan, sampai `akhir`. Instance digenerasi idempoten (tanggal_acuan).
 */
class KegiatanRecurring extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'kegiatan_recurring';

    public const FREKUENSI = ['mingguan' => 'Mingguan', 'bulanan' => 'Bulanan'];

    protected $fillable = [
        'tenant_id', 'nama', 'template_id', 'frekuensi', 'interval', 'mulai', 'akhir', 'is_active',
    ];

    protected $casts = [
        'interval' => 'integer',
        'mulai' => 'date',
        'akhir' => 'date',
        'is_active' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(KegiatanTemplate::class, 'template_id');
    }
}
