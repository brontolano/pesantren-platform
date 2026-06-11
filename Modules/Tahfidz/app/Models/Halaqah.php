<?php

namespace Modules\Tahfidz\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Database\Factories\HalaqahFactory;

class Halaqah extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'halaqah';

    protected $fillable = [
        'tenant_id',
        'nama',
        'musyrif_id',
        'jenis', // tahfidz|kitab|diniyah
        'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    protected static function newFactory(): HalaqahFactory
    {
        return HalaqahFactory::new();
    }

    public function musyrif(): BelongsTo
    {
        return $this->belongsTo(User::class, 'musyrif_id');
    }

    public function keanggotaan(): HasMany
    {
        return $this->hasMany(HalaqahSantri::class, 'halaqah_id');
    }

    public function keanggotaanAktif(): HasMany
    {
        return $this->keanggotaan()->whereNull('left_at');
    }

    public function santri(): BelongsToMany
    {
        return $this->belongsToMany(Santri::class, 'halaqah_santri', 'halaqah_id', 'santri_id')
            ->withPivot(['joined_at', 'left_at'])
            ->withTimestamps();
    }

    /** Anggota saat ini (keanggotaan belum di-soft-close). */
    public function santriAktif(): BelongsToMany
    {
        return $this->santri()->wherePivotNull('left_at');
    }

    public function hafalan(): HasMany
    {
        return $this->hasMany(HafalanTahfidz::class, 'halaqah_id');
    }
}
