<?php

namespace Modules\Akademik\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pelajaran extends Model
{
    use BelongsToTenant;

    protected $table = 'pelajaran';

    protected $fillable = [
        'tenant_id',
        'nama',
        'kode',
        'sks',
        'guru_pengampu_id',
    ];

    protected $casts = [
        'sks' => 'integer',
    ];

    public function guruPengampu(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_pengampu_id');
    }

    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'pelajaran_id');
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(Nilai::class, 'pelajaran_id');
    }
}
