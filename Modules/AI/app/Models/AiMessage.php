<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T-A050 — pesan percakapan WA (tabel TENANT).
 *
 * isi_terredaksi WAJIB sudah lewat PiiRedactor::redactPaksa sebelum disimpan.
 * wa_message_id UNIQUE = idempotensi webhook ganda.
 */
class AiMessage extends Model
{
    public const ARAH_MASUK = 'masuk';

    public const ARAH_KELUAR = 'keluar';

    public const UPDATED_AT = null;

    protected $table = 'ai_message';

    protected $fillable = [
        'conversation_id',
        'arah',
        'isi_terredaksi',
        'wa_message_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
