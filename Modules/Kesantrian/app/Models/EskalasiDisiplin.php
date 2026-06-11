<?php

namespace Modules\Kesantrian\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

/**
 * WP-09 — Record eskalasi (SP1/SP2/dst) per santri×periode×label.
 * Unique constraint DB menjamin SP tidak dobel (idempoten).
 */
class EskalasiDisiplin extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'eskalasi_disiplin';

    protected $fillable = [
        'tenant_id', 'santri_id', 'periode', 'label', 'poin', 'tanggal',
    ];

    protected $casts = [
        'poin' => 'integer',
        'tanggal' => 'date',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }
}
