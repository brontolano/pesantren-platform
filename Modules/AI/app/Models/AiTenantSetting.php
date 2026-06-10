<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * T-A010 — pengaturan AI per tenant, BER-VERSI (tabel TENANT).
 *
 * Tiap perubahan menghasilkan baris versi baru — JANGAN meng-update payload
 * baris lama (jejak audit siapa/kapan harus utuh). Maksimal satu baris
 * berstatus 'aktif' (partial unique index PostgreSQL). Mutasi status hanya
 * lewat TenantSettingService (RBAC + transaksi).
 */
class AiTenantSetting extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_ARSIP = 'arsip';

    protected $table = 'ai_tenant_setting';

    protected $fillable = [
        'versi',
        'payload',
        'status',
        'dibuat_oleh',
        'ringkasan_perubahan',
    ];

    protected $casts = [
        'versi' => 'integer',
        'payload' => 'array',
        'dibuat_oleh' => 'integer',
    ];

    public function approvals(): HasMany
    {
        return $this->hasMany(AiApproval::class, 'ai_tenant_setting_id');
    }
}
