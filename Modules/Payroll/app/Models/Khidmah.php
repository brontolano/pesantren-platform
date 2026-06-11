<?php

namespace Modules\Payroll\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * G2-11 — Poin khidmah/layanan per Employee per periode.
 * WP-13 — Tertelusur ke sumber (JurnalMengajar/PresensiStaf) via morph.
 * is_manual=true (default Eloquent — input bendahara via UI) TIDAK disentuh
 * agregasi ulang; baris aggregator dibuat dengan is_manual=false eksplisit.
 */
class Khidmah extends Model
{
    use BelongsToTenant;

    protected $table = 'khidmah';

    protected $fillable = [
        'tenant_id', 'employee_id', 'periode', 'jenis_sesi', 'poin',
        'sumber_type', 'sumber_id', 'is_manual', 'keterangan',
    ];

    protected $casts = [
        'poin' => 'decimal:2',
        'is_manual' => 'boolean',
    ];

    /**
     * Default Eloquent is_manual=true: SEMUA jalur create biasa (form
     * KhidmahResource, tinker) dianggap manual & aman dari agregasi ulang.
     * KhidmahAggregatorService SATU-SATUNYA yang menulis is_manual=false.
     */
    protected $attributes = [
        'is_manual' => true,
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function sumber(): MorphTo
    {
        return $this->morphTo('sumber', 'sumber_type', 'sumber_id');
    }
}
