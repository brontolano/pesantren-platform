<?php

namespace Modules\CMS\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WebsiteSection extends Model
{
    use BelongsToTenant;

    protected $table = 'website_sections';

    protected $fillable = [
        'tenant_id',
        'section_type',
        'sort_order',
        'is_active',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
