<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * T-A004 — override per tenant per agen (tabel CENTRAL):
 *  - enabled=false  → feature flag mati: agen tidak melayani tenant ini;
 *  - prompt_version_id terisi → pin versi tertentu (menang atas versi aktif).
 */
class AiPromptTenantOverride extends Model
{
    protected $table = 'ai_prompt_tenant_override';

    protected $fillable = [
        'tenant_id',
        'agent_key',
        'prompt_version_id',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'prompt_version_id' => 'integer',
    ];

    /** Paksa koneksi central agar konsisten meski dipanggil dari konteks tenant. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
