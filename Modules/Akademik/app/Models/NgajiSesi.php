<?php

namespace Modules\Akademik\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Akademik\Database\Factories\NgajiSesiFactory;
use Modules\Core\Concerns\Auditable;

/**
 * Issue #330 — Sesi ngaji kitab massal (bandongan/halaqah): satu sesi
 * (kitab, bab, tanggal, ustadz) diikuti banyak santri (peserta).
 */
class NgajiSesi extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    public const METODE = ['bandongan' => 'Bandongan', 'halaqah' => 'Halaqah'];

    protected $table = 'ngaji_sesi';

    protected $fillable = [
        'tenant_id',
        'kitab_id',
        'bab',
        'halaman_dari',
        'halaman_sampai',
        'tanggal',
        'ustadz_id',
        'metode',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'halaman_dari' => 'integer',
        'halaman_sampai' => 'integer',
    ];

    protected static function newFactory(): NgajiSesiFactory
    {
        return NgajiSesiFactory::new();
    }

    public function kitab(): BelongsTo
    {
        return $this->belongsTo(Kitab::class, 'kitab_id');
    }

    public function ustadz(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ustadz_id');
    }

    /**
     * Peserta via model pivot (bukan BelongsToMany->attach) — attach pivot
     * melewati event model sehingga tenant_id NULL (pola KeanggotaanRelationManager).
     */
    public function peserta(): HasMany
    {
        return $this->hasMany(NgajiSesiPeserta::class, 'ngaji_sesi_id');
    }
}
