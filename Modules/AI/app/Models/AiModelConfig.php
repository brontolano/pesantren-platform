<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi router model AI per (agent_key, tier) — tabel CENTRAL (K4).
 * agent_key NULL = baris default untuk tier tersebut.
 */
class AiModelConfig extends Model
{
    protected $table = 'ai_model_config';

    protected $fillable = [
        'agent_key',
        'tier',
        'provider',
        'model',
        'max_context_tokens',
        'options',
        'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'is_active' => 'boolean',
        'max_context_tokens' => 'integer',
    ];

    /** Paksa koneksi central agar konsisten meski dipanggil dari konteks tenant. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
