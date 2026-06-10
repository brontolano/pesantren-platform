<?php

namespace Modules\PPDB\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

/**
 * G3-20 — Log aktivitas CRM (campaign/kontak/catatan). `ref` = kunci idempotensi
 * campaign (mis. "daftar_ulang:2026") agar tak terkirim ganda.
 */
class CrmAktivitas extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'crm_aktivitas';

    public const TIPE = ['campaign' => 'Campaign', 'kontak' => 'Kontak', 'catatan' => 'Catatan'];

    protected $fillable = [
        'tenant_id', 'tipe', 'calon_santri_id', 'santri_id', 'kanal', 'ref',
        'status', 'keterangan', 'dikirim_pada', 'dibuat_oleh',
    ];

    protected $casts = [
        'dikirim_pada' => 'datetime',
    ];

    public function calon(): BelongsTo
    {
        return $this->belongsTo(CalonSantri::class, 'calon_santri_id');
    }
}
