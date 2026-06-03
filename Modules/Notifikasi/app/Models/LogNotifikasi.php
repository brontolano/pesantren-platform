<?php

namespace Modules\Notifikasi\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogNotifikasi extends Model
{
    use BelongsToTenant;

    protected $table = 'log_notifikasi';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'broadcast_id',
        'template_id',
        'jenis',
        'channel',
        'target',
        'status',
        'error_message',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(BroadcastWa::class, 'broadcast_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TemplateNotifikasi::class, 'template_id');
    }
}
