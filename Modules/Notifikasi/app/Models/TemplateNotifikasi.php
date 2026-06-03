<?php

namespace Modules\Notifikasi\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateNotifikasi extends Model
{
    use BelongsToTenant;

    protected $table = 'template_notifikasi';

    protected $fillable = [
        'tenant_id',
        'kode_template',
        'judul',
        'isi',
        'variable_dinamis',
        'channel',
        'is_active',
    ];

    protected $casts = [
        'variable_dinamis' => 'array',
        'is_active' => 'boolean',
    ];

    public function broadcasts(): HasMany
    {
        return $this->hasMany(BroadcastWa::class, 'template_id');
    }
}
