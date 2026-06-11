<?php

namespace Modules\Akademik\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Akademik\Database\Factories\KajianKitabFactory;
use Modules\Kesantrian\Models\Kelas;
use Modules\Tahfidz\Models\Halaqah;

class KajianKitab extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'kajian_kitab';

    protected $fillable = [
        'tenant_id',
        'kitab_id',
        'pengampu_id',
        'halaqah_id',
        'kelas_id',
        'periode_akademik_id',
        'metode', // sorogan|bandongan|wetonan
        'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    protected static function newFactory(): KajianKitabFactory
    {
        return KajianKitabFactory::new();
    }

    public function kitab(): BelongsTo
    {
        return $this->belongsTo(Kitab::class, 'kitab_id');
    }

    public function pengampu(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengampu_id');
    }

    public function halaqah(): BelongsTo
    {
        return $this->belongsTo(Halaqah::class, 'halaqah_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function periodeAkademik(): BelongsTo
    {
        return $this->belongsTo(PeriodeAkademik::class, 'periode_akademik_id');
    }

    public function capaian(): HasMany
    {
        return $this->hasMany(CapaianKitab::class, 'kajian_kitab_id');
    }
}
