<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T-A042 — draf ikhtisar materi kitab / kerangka kultum (tabel TENANT).
 *
 * Lifecycle status: antri → menyusun → draf → disahkan (manusia) | gagal.
 * Mutasi pipeline (proses/sahkan/edit isi) hanya lewat IkhtisarGenerator —
 * pagar #3: AI menyusun draf, MANUSIA yang mengesahkan.
 *
 * isi per jenis (lihat komentar migrasi): poin ikhtisar membawa sitasi_idx
 * (indeks kolom `sitasi` — poin → sumber tertelusur); kerangka kultum hanya
 * STRUKTUR + penanda [PERIKSA DALIL], tanpa konten dalil; `sitasi` = daftar
 * sumber deterministik pipeline (bukan dari model).
 */
class AiDrafIkhtisar extends Model
{
    public const JENIS_IKHTISAR = 'ikhtisar';

    public const JENIS_KULTUM = 'kultum';

    public const JENIS = [self::JENIS_IKHTISAR, self::JENIS_KULTUM];

    public const STATUS_ANTRI = 'antri';

    public const STATUS_MENYUSUN = 'menyusun';

    public const STATUS_DRAF = 'draf';

    public const STATUS_DISAHKAN = 'disahkan';

    public const STATUS_GAGAL = 'gagal';

    protected $table = 'ai_draf_ikhtisar';

    protected $fillable = [
        'jenis',
        'judul',
        'source_id',
        'bab',
        'tema',
        'durasi_menit',
        'status',
        'isi',
        'sitasi',
        'error_ringkas',
        'dibuat_oleh',
        'disahkan_oleh',
        'disahkan_pada',
        'idempotency_key',
    ];

    protected $casts = [
        'source_id' => 'integer',
        'durasi_menit' => 'integer',
        'isi' => 'array',
        'sitasi' => 'array',
        'dibuat_oleh' => 'integer',
        'disahkan_oleh' => 'integer',
        'disahkan_pada' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeSource::class, 'source_id');
    }
}
