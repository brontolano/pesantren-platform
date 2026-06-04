<?php

namespace Modules\CMS\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gallery extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'galleries';

    protected $fillable = [
        'tenant_id',
        'title',
        'slug',
        'description',
        'cover_image_url',
        'is_published',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(GalleryImage::class, 'gallery_id')->orderBy('sort_order');
    }
}
