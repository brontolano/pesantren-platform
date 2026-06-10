<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * T-A004 — katalog agen AI (tabel CENTRAL). Satu baris per agen produk
 * (mis. uji-internal; agen produk nyata menyusul di epic fiturnya).
 */
class AiAgent extends Model
{
    protected $table = 'ai_agent';

    protected $fillable = [
        'key',
        'nama',
        'deskripsi',
        'is_active',
        'default_tier',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(AiPromptVersion::class, 'agent_key', 'key');
    }

    /** Paksa koneksi central agar konsisten meski dipanggil dari konteks tenant. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
