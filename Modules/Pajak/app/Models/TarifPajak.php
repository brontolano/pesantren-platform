<?php

namespace Modules\Pajak\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\Auditable;

class TarifPajak extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'tarif_pajak';

    protected $fillable = [
        'tenant_id',
        'kode',
        'nama',
        'tarif_persen',
        'ambang_min',
        'dasar',
        'is_active',
    ];

    protected $casts = [
        'tarif_persen' => 'decimal:2',
        'ambang_min' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
