<?php

namespace Modules\PPDB\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

/**
 * G3-20 — Penawaran biaya (quotation) dari SkemaBiaya. READ-ONLY terhadap
 * SkemaBiaya (snapshot items) — TIDAK menghasilkan Tagihan/jurnal.
 */
class CrmPenawaran extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'crm_penawaran';

    protected $fillable = [
        'tenant_id', 'calon_santri_id', 'santri_id', 'nama_prospek', 'tahun_ajaran',
        'items', 'total', 'status', 'keterangan', 'dibuat_oleh',
    ];

    protected $casts = [
        'items' => 'array',
        'total' => 'decimal:2',
    ];

    public function calon(): BelongsTo
    {
        return $this->belongsTo(CalonSantri::class, 'calon_santri_id');
    }
}
