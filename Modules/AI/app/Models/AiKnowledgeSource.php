<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * T-A030 — sumber pengetahuan RAG per tenant (tabel TENANT).
 *
 * Lifecycle status: diunggah → memproses → siap | gagal.
 * Mutasi pipeline (proses/re-ingest/hapus tuntas) hanya lewat IngestService.
 */
class AiKnowledgeSource extends Model
{
    public const STATUS_DIUNGGAH = 'diunggah';

    public const STATUS_MEMPROSES = 'memproses';

    public const STATUS_SIAP = 'siap';

    public const STATUS_GAGAL = 'gagal';

    public const KATEGORI = ['kurikulum', 'sop', 'ppdb', 'faq', 'lainnya'];

    protected $table = 'ai_knowledge_source';

    protected $fillable = [
        'judul',
        'kategori',
        'path_file',
        'mime',
        'status',
        'error_ringkas',
        'jumlah_chunk',
        'aktif',
        'versi_ingest',
        'diunggah_oleh',
    ];

    protected $casts = [
        'jumlah_chunk' => 'integer',
        'aktif' => 'boolean',
        'versi_ingest' => 'integer',
        'diunggah_oleh' => 'integer',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(AiChunk::class, 'source_id');
    }
}
