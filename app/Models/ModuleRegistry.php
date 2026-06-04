<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registry modul platform (selalu di koneksi CENTRAL).
 */
class ModuleRegistry extends Model
{
    protected $table = 'module_registry';

    protected $fillable = ['key', 'label', 'status', 'is_core', 'sort'];

    protected $casts = [
        'is_core' => 'boolean',
        'sort' => 'integer',
    ];

    /** Paksa koneksi central agar konsisten meski dipanggil dari konteks tenant. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
