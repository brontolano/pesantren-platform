<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak audit aksi media (DB tenant). Append-only.
 */
class MediaAudit extends Model
{
    use BelongsToTenant;

    protected $table = 'media_audit';

    protected $fillable = [
        'tenant_id',
        'action',
        'media_file_id',
        'file_name',
        'size_bytes',
        'user_id',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
