<?php

namespace Modules\Keuangan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\Auditable;

/**
 * G3-21 — Profil loket POS (per loket): akun kas/QRIS, tarif poin loyalitas.
 */
class PosProfil extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'pos_profil';

    protected $fillable = [
        'tenant_id', 'kode', 'nama', 'pos_jenis', 'akun_kas_id', 'akun_qris_id', 'tarif_poin', 'is_active',
    ];

    protected $casts = [
        'tarif_poin' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}
