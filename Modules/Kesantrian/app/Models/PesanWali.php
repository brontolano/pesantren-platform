<?php

namespace Modules\Kesantrian\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

/**
 * F3-PWA — Pesan guru → wali santri.
 *
 * Alur status: (draft) → menunggu_moderasi (jenis 'perhatian' saat saklar
 * moderasi ON) → terkirim | ditolak. Jenis apresiasi/info selalu langsung
 * terkirim. Pengiriman: notifikasi Portal Wali (LogNotifikasi channel
 * database) + WA wali via NotifDispatcher (fire-and-forget).
 */
class PesanWali extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'pesan_wali';

    public const JENIS = ['apresiasi', 'perhatian', 'info'];

    public const STATUS = ['draft', 'menunggu_moderasi', 'terkirim', 'ditolak'];

    public const DIKIRIM_VIA = ['portal', 'wa', 'keduanya'];

    protected $fillable = [
        'tenant_id', 'guru_id', 'santri_id', 'jenis', 'isi', 'status',
        'dikirim_via', 'moderated_by', 'alasan_tolak', 'sent_at', 'client_ref',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }
}
