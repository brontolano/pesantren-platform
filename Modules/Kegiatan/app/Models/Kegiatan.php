<?php

namespace Modules\Kegiatan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
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
        // G3-19 — penaut template/recurring (additif).
        'template_id', 'recurring_id', 'tanggal_acuan',
        // WP-11 — pasaran/kilatan: peserta luar [{nama, asal, hadir}] di JSON,
        // TIDAK menyentuh tabel santri/presensi_kegiatan.
        'peserta_luar',
    ];

    protected $casts = [
        'mulai' => 'datetime',
        'selesai' => 'datetime',
        'tanggal_acuan' => 'date',
        'peserta_luar' => 'array',
    ];

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penanggung_jawab_id');
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(PresensiKegiatan::class, 'kegiatan_id');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(KegiatanPhase::class, 'kegiatan_id')->orderBy('urutan');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(KegiatanTemplate::class, 'template_id');
    }
}
