<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FAQ-Mining — draf FAQ hasil penambangan pertanyaan berulang (tabel TENANT).
 *
 * Siklus status: usulan → dijawab → disahkan | diabaikan.
 * Mutasi status HANYA lewat FaqMiner (isiJawaban/sahkan/abaikan) — pengesahan
 * memerlukan permission `ai.pengetahuan.kelola` + jawaban terisi, lalu teks
 * FAQ diingest ke Sumber Pengetahuan kategori 'faq' (source_id terisi).
 *
 * pertanyaan_perwakilan & variasi SUDAH terredaksi PII — jangan pernah
 * menulis teks mentah ke kolom ini (lihat FaqMiner::tambang).
 */
class AiFaqDraf extends Model
{
    public const STATUS_USULAN = 'usulan';

    public const STATUS_DIJAWAB = 'dijawab';

    public const STATUS_DISAHKAN = 'disahkan';

    public const STATUS_DIABAIKAN = 'diabaikan';

    public const SUMBER_ESKALASI = 'eskalasi';

    public const SUMBER_PERCAKAPAN = 'percakapan';

    protected $table = 'ai_faq_draf';

    protected $fillable = [
        'pertanyaan_perwakilan',
        'pertanyaan_hash',
        'variasi',
        'frekuensi',
        'sumber',
        'jawaban',
        'status',
        'source_id',
        'dibuat_periode',
        'disahkan_oleh',
        'disahkan_pada',
    ];

    protected function casts(): array
    {
        return [
            'variasi' => 'array',
            'frekuensi' => 'integer',
            'source_id' => 'integer',
            'dibuat_periode' => 'date',
            'disahkan_oleh' => 'integer',
            'disahkan_pada' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeSource::class, 'source_id');
    }
}
