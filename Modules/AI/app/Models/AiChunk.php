<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T-A030 — potongan dokumen tersanitasi + vektor embedding (tabel TENANT).
 *
 * `konten` SUDAH melewati ContentSanitizer::sanitize (data, bukan perintah).
 * Vektor disimpan ganda: kolom `embedding` (pgvector, bila extension ada)
 * untuk ANN search T-A031, dan `embedding_json` (jsonb) sebagai fallback
 * penyimpanan di lingkungan tanpa pgvector.
 */
class AiChunk extends Model
{
    protected $table = 'ai_chunk';

    protected $fillable = [
        'source_id',
        'urutan',
        'konten',
        'metadata',
        'embedding_json',
    ];

    protected $casts = [
        'source_id' => 'integer',
        'urutan' => 'integer',
        'metadata' => 'array',
        'embedding_json' => 'array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeSource::class, 'source_id');
    }
}
