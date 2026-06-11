<?php

namespace Modules\Kesantrian\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Auditable;

/**
 * WP-09 — Master jenis pelanggaran (per tenant, bebas diubah).
 * Kategori 'prestasi' berpoin NEGATIF (penyeimbang reward-punishment).
 */
class JenisPelanggaran extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'jenis_pelanggaran';

    public const KATEGORI = ['ringan', 'sedang', 'berat', 'prestasi'];

    protected $fillable = [
        'tenant_id', 'nama', 'kategori', 'poin', 'sanksi_default', 'aktif',
    ];

    protected $casts = [
        'poin' => 'integer',
        'aktif' => 'boolean',
    ];

    public function pelanggaran(): HasMany
    {
        return $this->hasMany(PelanggaranSantri::class, 'jenis_pelanggaran_id');
    }

    public function isPrestasi(): bool
    {
        return $this->kategori === 'prestasi';
    }
}
