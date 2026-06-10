<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T-A010 — persetujuan kyai/pimpinan atas satu versi ai_tenant_setting
 * (tabel TENANT). ringkasan_hash = sidik jari kebijakan yang dibaca saat
 * memutuskan; verifikasi ulang dilakukan TenantSettingService::putuskan().
 */
class AiApproval extends Model
{
    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_DITOLAK = 'ditolak';

    protected $table = 'ai_approval';

    protected $fillable = [
        'ai_tenant_setting_id',
        'ringkasan_hash',
        'status',
        'disetujui_oleh',
        'alasan',
        'decided_at',
    ];

    protected $casts = [
        'ai_tenant_setting_id' => 'integer',
        'disetujui_oleh' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(AiTenantSetting::class, 'ai_tenant_setting_id');
    }
}
