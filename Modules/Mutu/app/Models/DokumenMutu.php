<?php

namespace Modules\Mutu\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DokumenMutu extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'dokumen_mutu';

    protected $fillable = [
        'tenant_id', 'judul', 'jenis', 'regulasi_acuan', 'berkas_url', 'status_kelengkapan', 'created_by',
        // Alur approval + versioning (#27).
        'status_approval', 'versi',
        'diajukan_oleh', 'diajukan_pada', 'direview_oleh', 'direview_pada', 'catatan_review',
    ];

    protected $casts = [
        'versi' => 'integer',
        'diajukan_pada' => 'datetime',
        'direview_pada' => 'datetime',
    ];

    /** Riwayat versi (snapshot) dokumen ini. */
    public function versiRiwayat(): HasMany
    {
        return $this->hasMany(DokumenMutuVersi::class)->orderBy('versi');
    }
}
