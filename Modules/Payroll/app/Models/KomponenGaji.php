<?php

namespace Modules\Payroll\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * G2-10 — Komponen struktur gaji (tetap/tunjangan/potongan) per Employee.
 */
class KomponenGaji extends Model
{
    use BelongsToTenant;

    protected $table = 'komponen_gaji';

    protected $fillable = [
        'tenant_id', 'employee_id', 'nama', 'tipe', 'nominal', 'aktif',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'aktif' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
