<?php

namespace Modules\Akademik\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Akademik\Database\Factories\KalenderAkademikFactory;

/**
 * WP-02 — Entri kalender akademik (rentang tanggal).
 * lembaga_id NULL = berlaku semua lembaga (pola WP-01).
 */
class KalenderAkademik extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const JENIS = ['libur_nasional', 'libur_pondok', 'ramadhan', 'ujian', 'pasaran', 'kegiatan'];

    /** Jenis yang MENGURANGI hari efektif. ujian|pasaran|kegiatan tidak. */
    public const JENIS_TIDAK_EFEKTIF = ['libur_nasional', 'libur_pondok', 'ramadhan'];

    protected $table = 'kalender_akademik';

    protected $fillable = [
        'tenant_id',
        'lembaga_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'jenis',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    protected static function newFactory(): KalenderAkademikFactory
    {
        return KalenderAkademikFactory::new();
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class, 'lembaga_id');
    }
}
