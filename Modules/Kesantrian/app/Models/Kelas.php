<?php

namespace Modules\Kesantrian\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kelas extends Model
{
    use BelongsToTenant;

    protected $table = 'kelas';

    protected $fillable = [
        'tenant_id',
        'nama_kelas',
        'tingkat',
        'jurusan',
        'capacity',
        'wali_kelas_id',
        'is_active',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wali_kelas_id');
    }

    public function santri(): HasMany
    {
        return $this->hasMany(Santri::class, 'kelas_id');
    }

    public function penempatanKelas(): HasMany
    {
        return $this->hasMany(PenempatanKelas::class, 'kelas_id');
    }

    public function jumlahSantriAktif(): int
    {
        return $this->santri()->where('status', 'aktif')->count();
    }

    public function sisaKapasitas(): int
    {
        return $this->capacity - $this->jumlahSantriAktif();
    }
}
