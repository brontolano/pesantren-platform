<?php

namespace Modules\Akademik\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;
use Modules\Kesantrian\Models\Santri;

/**
 * G3-18 — Presensi kelas harian santri. Status: hadir/izin/sakit/alpa.
 */
class PresensiSantri extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'presensi_santri';

    public const STATUS = ['hadir', 'izin', 'sakit', 'alpa'];

    /** Status yang dihitung sebagai "hadir efektif" untuk threshold rapor. */
    public const HADIR_EFEKTIF = ['hadir'];

    protected $fillable = [
        'tenant_id', 'santri_id', 'kelas_id', 'tanggal', 'status', 'keterangan', 'dicatat_oleh',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }
}
