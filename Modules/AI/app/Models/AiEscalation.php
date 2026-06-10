<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * T-A020 — antrean "Tanya Ustadz" (tabel TENANT).
 *
 * pertanyaan_ringkas WAJIB sudah terredaksi PII sebelum disimpan — penulisan
 * hanya lewat PolicyEngine (yang memanggil PiiRedactor). UI inbox = T-A023.
 */
class AiEscalation extends Model
{
    public const STATUS_BARU = 'baru';

    public const STATUS_DITUGASKAN = 'ditugaskan';

    public const STATUS_DIJAWAB = 'dijawab';

    public const STATUS_DITUTUP = 'ditutup';

    protected $table = 'ai_escalation';

    protected $fillable = [
        'conversation_ref',
        'kanal',
        'pertanyaan_ringkas',
        'kategori',
        'status',
        'ditugaskan_ke',
        'jawaban',
    ];

    protected $casts = [
        'ditugaskan_ke' => 'integer',
    ];
}
