<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * T-A020 — kebijakan guardrails per tenant (tabel TENANT).
 *
 * sumber 'preset' = dari berkas seed tinjauan ustadz (GATE-3, PRD §12.2);
 * sumber 'tenant' = milik pesantren, termasuk mirror blacklist_topik dari
 * ai_tenant_setting (PolicyEngine::syncDariSetting).
 */
class AiPolicy extends Model
{
    public const SUMBER_PRESET = 'preset';

    public const SUMBER_TENANT = 'tenant';

    /** Nama kanonik policy tenant tempat mirror blacklist_topik setting. */
    public const NAMA_KEBIJAKAN_PESANTREN = 'Kebijakan Pesantren';

    protected $table = 'ai_policy';

    protected $fillable = ['nama', 'sumber', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];

    public function rules(): HasMany
    {
        return $this->hasMany(AiPolicyRule::class, 'policy_id');
    }
}
