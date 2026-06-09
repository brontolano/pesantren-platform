<?php

namespace Modules\Keuangan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;
use Modules\Ledger\Models\DivisiUnit;
use Modules\Ledger\Models\SumberDana;

/**
 * G2-16 — Skema Biaya (Pricing Rule). Tarif baku generate Tagihan.
 */
class SkemaBiaya extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'skema_biaya';

    protected $fillable = [
        'tenant_id', 'tahun_ajaran', 'divisi_id', 'jenis_tagihan',
        'nominal', 'sumber_dana_id', 'keterangan', 'aktif',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'aktif' => 'boolean',
    ];

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(DivisiUnit::class, 'divisi_id');
    }

    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(SumberDana::class, 'sumber_dana_id');
    }
}
