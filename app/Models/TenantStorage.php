<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kuota penyimpanan tenant (CENTRAL only).
 */
class TenantStorage extends Model
{
    protected $table = 'tenant_storage';

    protected $fillable = ['tenant_id', 'quota_gb'];

    protected $casts = [
        'quota_gb' => 'integer',
    ];

    /** Paksa koneksi central agar konsisten meski dipanggil dari konteks tenant. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
