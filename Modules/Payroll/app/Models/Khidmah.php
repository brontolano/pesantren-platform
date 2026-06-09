<?php

namespace Modules\Payroll\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * G2-11 — Poin khidmah/layanan per Employee per periode.
 */
class Khidmah extends Model
{
    use BelongsToTenant;

    protected $table = 'khidmah';

    protected $fillable = [
        'tenant_id', 'employee_id', 'periode', 'poin', 'keterangan',
    ];

    protected $casts = [
        'poin' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
