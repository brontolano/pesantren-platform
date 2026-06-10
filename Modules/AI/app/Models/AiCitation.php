<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T-A031 — sitasi deterministik per jawaban RAG (tabel TENANT).
 *
 * Append-only (hanya created_at); penulisan hanya lewat RagAnswerer dari
 * chunk yang benar-benar dipakai pipeline — BUKAN dari teks model.
 */
class AiCitation extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ai_citation';

    protected $fillable = [
        'audit_log_id',
        'source_id',
        'chunk_id',
        'urutan',
        'judul',
        'bab',
        'halaman',
        'skor',
        'created_at',
    ];

    protected $casts = [
        'audit_log_id' => 'string', // ULID mengikuti pk ai_audit_log
        'source_id' => 'integer',
        'chunk_id' => 'integer',
        'urutan' => 'integer',
        'halaman' => 'integer',
        'skor' => 'float',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeSource::class, 'source_id');
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(AiChunk::class, 'chunk_id');
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AiAuditLog::class, 'audit_log_id');
    }
}
