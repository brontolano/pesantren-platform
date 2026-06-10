<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * T-A020 — antrean "Tanya Ustadz" (tabel TENANT).
 *
 * pertanyaan_ringkas WAJIB sudah terredaksi PII sebelum disimpan — penulisan
 * hanya lewat PolicyEngine (yang memanggil PiiRedactor).
 *
 * T-A023 — siklus hidup baru→ditugaskan→dijawab→ditutup + SLA & pengingat.
 * Mutasi status HANYA lewat EscalationService (transaksi + notifikasi).
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
        'idempotency_key',
        'kanal',
        'pertanyaan_ringkas',
        'kategori',
        'status',
        'ditugaskan_ke',
        'jawaban',
        'sla_deadline',
        'diingatkan_pada',
        'dijawab_oleh',
        'dijawab_pada',
        'ditutup_pada',
    ];

    protected $casts = [
        'ditugaskan_ke' => 'integer',
        'dijawab_oleh' => 'integer',
        'sla_deadline' => 'datetime',
        'diingatkan_pada' => 'datetime',
        'dijawab_pada' => 'datetime',
        'ditutup_pada' => 'datetime',
    ];
}
