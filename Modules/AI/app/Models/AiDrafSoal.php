<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T-A040 — paket draf soal hasil Generator Soal & Kisi-kisi (tabel TENANT).
 *
 * Lifecycle status: antri → menyusun → draf → disahkan (manusia) | gagal.
 * Mutasi pipeline (proses/sahkan/edit butir) hanya lewat SoalGenerator —
 * pagar #3: AI menyusun draf, MANUSIA yang mengesahkan.
 */
class AiDrafSoal extends Model
{
    public const STATUS_ANTRI = 'antri';

    public const STATUS_MENYUSUN = 'menyusun';

    public const STATUS_DRAF = 'draf';

    public const STATUS_DISAHKAN = 'disahkan';

    public const STATUS_GAGAL = 'gagal';

    public const TIPE = ['pilihan_ganda', 'esai', 'campuran'];

    /** Taksonomi kognitif sederhana (C1 Mengingat … C6 Mencipta). */
    public const TINGKAT_KOGNITIF = ['C1', 'C2', 'C3', 'C4', 'C5', 'C6'];

    protected $table = 'ai_draf_soal';

    protected $fillable = [
        'judul',
        'mapel',
        'source_id',
        'bab',
        'jenjang',
        'jumlah',
        'tipe',
        'tingkat_kognitif',
        'status',
        'butir',
        'error_ringkas',
        'dibuat_oleh',
        'disahkan_oleh',
        'disahkan_pada',
        'idempotency_key',
    ];

    protected $casts = [
        'source_id' => 'integer',
        'jumlah' => 'integer',
        'butir' => 'array',
        'dibuat_oleh' => 'integer',
        'disahkan_oleh' => 'integer',
        'disahkan_pada' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeSource::class, 'source_id');
    }
}
