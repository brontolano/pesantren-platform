<?php

namespace Modules\Mutu\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Auditable;

/**
 * G3-17 — Inspeksi/audit mutu per area (wadah temuan).
 */
class MutuInspeksi extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'mutu_inspeksi';

    protected $fillable = [
        'tenant_id', 'judul', 'area', 'tanggal', 'auditor', 'ringkasan', 'status', 'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function temuan(): HasMany
    {
        return $this->hasMany(MutuTemuan::class, 'inspeksi_id');
    }
}
