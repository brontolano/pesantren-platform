<?php

namespace Modules\Payroll\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Auditable;
use Modules\Payroll\Observers\JabatanObserver;

/**
 * WP-03 — Master Jabatan per tenant. role_spatie = nama role yang SUDAH ada
 * di repo (mapping akses otomatis saat penugasan); NULL = tanpa akses otomatis.
 * Observer me-resync role user saat role_spatie diubah.
 */
#[ObservedBy(JabatanObserver::class)]
class Jabatan extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'jabatan';

    public const KATEGORI = [
        'struktural' => 'Struktural',
        'pengajar' => 'Pengajar',
        'kepengasuhan' => 'Kepengasuhan',
        'pengurus_santri' => 'Pengurus Santri',
        'staf' => 'Staf',
    ];

    protected $fillable = [
        'tenant_id', 'nama', 'kategori', 'role_spatie', 'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function penugasan(): HasMany
    {
        return $this->hasMany(PenugasanPegawai::class, 'jabatan_id');
    }
}
