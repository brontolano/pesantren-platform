<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T-A041 — draf RPP / modul ajar hasil Generator RPP (tabel TENANT).
 *
 * Lifecycle status: antri → menyusun → draf → disahkan (manusia) | gagal.
 * Mutasi pipeline (proses/sahkan/edit isi) hanya lewat RppGenerator —
 * pagar #3: AI menyusun draf, MANUSIA yang mengesahkan.
 *
 * isi = array {kunci, judul, konten, sitasi[]} per seksi template;
 * sitasi per seksi = label deterministik pipeline (bukan dari model).
 */
class AiDrafRpp extends Model
{
    public const STATUS_ANTRI = 'antri';

    public const STATUS_MENYUSUN = 'menyusun';

    public const STATUS_DRAF = 'draf';

    public const STATUS_DISAHKAN = 'disahkan';

    public const STATUS_GAGAL = 'gagal';

    protected $table = 'ai_draf_rpp';

    protected $fillable = [
        'template_id',
        'judul',
        'mapel',
        'kelas',
        'source_id',
        'input_guru',
        'status',
        'isi',
        'error_ringkas',
        'dibuat_oleh',
        'disahkan_oleh',
        'disahkan_pada',
        'idempotency_key',
    ];

    protected $casts = [
        'template_id' => 'integer',
        'source_id' => 'integer',
        'input_guru' => 'array',
        'isi' => 'array',
        'dibuat_oleh' => 'integer',
        'disahkan_oleh' => 'integer',
        'disahkan_pada' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(AiTemplateRpp::class, 'template_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeSource::class, 'source_id');
    }
}
