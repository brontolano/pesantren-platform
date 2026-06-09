<?php

namespace Modules\Payroll\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * G2-11 — Presensi staf harian (Employee). Terpisah dari PresensiKegiatan.
 */
class PresensiStaf extends Model
{
    use BelongsToTenant;

    protected $table = 'presensi_staf';

    protected $fillable = [
        'tenant_id', 'employee_id', 'tanggal', 'status', 'jam_masuk', 'jam_keluar', 'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
