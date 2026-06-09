<?php

namespace Modules\Payroll\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Concerns\Approvable;
use Modules\Core\Concerns\Auditable;

class SlipGaji extends Model
{
    use Approvable, Auditable, BelongsToTenant, SoftDeletes;

    protected $table = 'slip_gaji';

    protected $fillable = [
        'tenant_id', 'users_id', 'periode',
        'honor_pokok', 'tunjangan', 'poin', 'tarif_poin', 'honor_poin', 'honor_output',
        'bruto', 'pph21_dipotong', 'bpjs_dipotong', 'neto', 'jurnal_id', 'status', 'created_by',
    ];

    protected $casts = [
        'honor_pokok' => 'decimal:2',
        'tunjangan' => 'decimal:2',
        'tarif_poin' => 'decimal:2',
        'honor_poin' => 'decimal:2',
        'honor_output' => 'decimal:2',
        'bruto' => 'decimal:2',
        'pph21_dipotong' => 'decimal:2',
        'bpjs_dipotong' => 'decimal:2',
        'neto' => 'decimal:2',
    ];

    public function ptk(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }
}
