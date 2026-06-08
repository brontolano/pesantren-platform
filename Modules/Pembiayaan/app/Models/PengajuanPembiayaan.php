<?php

namespace Modules\Pembiayaan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PengajuanPembiayaan extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'pengajuan_pembiayaan';

    protected $fillable = [
        'tenant_id', 'bank_tujuan', 'produk', 'plafon', 'tenor', 'margin',
        'ebitda_tahunan', 'dscr', 'zona', 'aset_agunan_id', 'status',
        'esignature_pembina', 'created_by',
    ];

    protected $casts = [
        'plafon' => 'decimal:2',
        'margin' => 'decimal:2',
        'ebitda_tahunan' => 'decimal:2',
        'dscr' => 'decimal:2',
    ];

    public function agunan(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_agunan_id');
    }
}
