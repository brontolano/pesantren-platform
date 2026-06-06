<?php

namespace Modules\Kesantrian\Models;

use App\Models\AccessWali;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Asrama\Models\BiayaAsrama;
use Modules\Asrama\Models\PenempatanAsrama;

class Santri extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'santri';

    protected $fillable = [
        'tenant_id',
        'nis',
        'nama',
        'tanggal_lahir',
        'tempat_lahir',
        'jenis_kelamin',
        'agama',
        'asal_sekolah',
        'alamat',
        'no_hp_wali',
        'email_wali',
        'nama_ayah',
        'nama_ibu',
        'pekerjaan_ayah',
        'pekerjaan_ibu',
        'status',
        'tahun_angkatan',
        'kelas_id',
        'photo_url',
        'wali_user_id',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
    ];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function waliUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wali_user_id');
    }

    public function accessWali(): HasMany
    {
        return $this->hasMany(AccessWali::class, 'santri_id');
    }

    public function waliPortal(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'access_wali', 'santri_id', 'wali_id')
            ->withPivot(['tenant_id', 'relationship_type', 'is_primary'])
            ->withTimestamps();
    }

    public function penempatanKelas(): HasMany
    {
        return $this->hasMany(PenempatanKelas::class, 'santri_id');
    }

    public function penempatanAsrama(): HasMany
    {
        return $this->hasMany(PenempatanAsrama::class, 'santri_id');
    }

    public function penempatanAsramaAktif(): HasOne
    {
        return $this->hasOne(PenempatanAsrama::class, 'santri_id')
            ->where('is_current', true)
            ->latestOfMany('tanggal_masuk');
    }

    public function biayaAsrama(): HasMany
    {
        return $this->hasMany(BiayaAsrama::class, 'santri_id');
    }

    public function penempatanAktif(): HasOne
    {
        return $this->hasOne(PenempatanKelas::class, 'santri_id')
            ->where('is_current', true)
            ->latestOfMany('tanggal_penempatan');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(StatusSantri::class, 'santri_id')->orderByDesc('tanggal_status');
    }

    public function alumni(): HasOne
    {
        return $this->hasOne(Alumni::class, 'santri_id');
    }

    public function tagihan(): HasMany
    {
        return $this->hasMany(\Modules\Keuangan\Models\Tagihan::class, 'santri_id');
    }

    public function isAktif(): bool
    {
        return $this->status === 'aktif';
    }
}
