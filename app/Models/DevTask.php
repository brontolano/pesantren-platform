<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Task pengembangan (CENTRAL only). Unit terkecil yang dipantau progresnya.
 */
class DevTask extends Model
{
    protected $fillable = [
        'dev_project_id',
        'title',
        'detail',
        'current_state',
        'phase',
        'status',
        'priority',
        'progress',
        'effort',
        'sort',
    ];

    protected $casts = [
        'progress' => 'integer',
        'sort' => 'integer',
    ];

    /** Paksa koneksi central agar konsisten meski dipanggil dari konteks tenant. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DevProject::class, 'dev_project_id');
    }
}
