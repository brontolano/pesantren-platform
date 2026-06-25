<?php

namespace Modules\Kesantrian\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Akademik\Models\Lembaga;

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
        'lembaga_id',
        'is_active',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    /** WP-01: NULL = lembaga default tenant (data lama tetap sah). */
    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class, 'lembaga_id');
    }

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
