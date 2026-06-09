<?php

namespace Modules\Mutu\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot satu versi DokumenMutu — riwayat tak-bisa-diubah untuk audit mutu.
 */
class DokumenMutuVersi extends Model
{
    use BelongsToTenant;

    protected $table = 'dokumen_mutu_versi';

    protected $fillable = [
        'tenant_id', 'dokumen_mutu_id', 'versi', 'judul', 'jenis',
        'regulasi_acuan', 'berkas_url', 'status_approval', 'catatan', 'dibuat_oleh',
    ];

    protected $casts = [
        'versi' => 'integer',
    ];

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(DokumenMutu::class, 'dokumen_mutu_id');
    }
}
