<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * T-A050 — percakapan WA inbound (tabel TENANT).
 *
 * nomor_wa_hash = sha256(nomor.APP_KEY); nomor_wa_terenkripsi = Crypt.
 * JANGAN pernah menambahkan kolom/log nomor polos.
 */
class AiConversation extends Model
{
    public const STATUS_AKTIF = 'aktif';

    public const STATUS_HANDOFF = 'handoff';

    public const STATUS_DITUTUP = 'ditutup';

    protected $table = 'ai_conversation';

    protected $fillable = [
        'kanal',
        'nomor_wa_hash',
        'nomor_wa_terenkripsi',
        'wali_user_id',
        'santri_id',
        'status',
        'terakhir_aktif',
        'opt_out',
    ];

    protected function casts(): array
    {
        return [
            'terakhir_aktif' => 'datetime',
            'opt_out' => 'boolean',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id');
    }

    /** Hash nomor WA satu pintu — sha256 dengan APP_KEY sebagai salt. */
    public static function hashNomor(string $nomor): string
    {
        return hash('sha256', $nomor.(string) config('app.key'));
    }
}
