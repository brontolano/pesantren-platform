<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * File media milik tenant (disimpan di DB tenant + storage terisolasi per tenant).
 */
class MediaFile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'path',
        'original_name',
        'mime',
        'size_bytes',
        'folder',
        'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];
}
