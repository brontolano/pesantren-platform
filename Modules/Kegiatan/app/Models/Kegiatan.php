<?php

namespace Modules\Kegiatan\Models;

use App\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kegiatan extends Model
{
    use BelongsToTenant;

    protected $table = 'kegiatan';

    protected $fillable = [
        'tenant_id', 'nama', 'deskripsi', 'jenis',
        'mulai', 'selesai', 'lokasi', 'status', 'penanggung_jawab_id',
    ];

    protected $casts = [
        'mulai'   => 'datetime',
        'selesai' => 'datetime',
    ];

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penanggung_jawab_id');
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(PresensiKegiatan::class, 'kegiatan_id');
    }
}
