<?php

namespace Modules\Notifikasi\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BroadcastWa extends Model
{
    use BelongsToTenant;

    protected $table = 'broadcast_wa';

    protected $fillable = [
        'tenant_id',
        'template_id',
        'target_group',
        'filter_criteria',
        'status',
        'tanggal_kirim',
        'created_by',
        'attempt_count',
        'last_error_message',
    ];

    protected $casts = [
        'filter_criteria' => 'array',
        'tanggal_kirim' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(TemplateNotifikasi::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LogNotifikasi::class, 'broadcast_id');
    }
}
