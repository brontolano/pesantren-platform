<?php

namespace Modules\CMS\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nomanur\FilamentSeoPro\Traits\HasSeo;

class Article extends Model
{
    use BelongsToTenant;

    // SEO Pro: relasi polimorfik `seo` (tabel seo_meta) — sumber SEO terkaya
    // (Open Graph, Twitter, schema_type, robots, canonical). Kolom lama
    // meta_title/meta_description TETAP dihormati sebagai fallback bila relasi
    // seo belum diisi editor (lihat TenantWebsiteService::buildArticleSeoPayload).
    use HasSeo;
    use SoftDeletes;

    protected $table = 'articles';

    protected $fillable = [
        'tenant_id',
        'title',
        'slug',
        'category',
        'excerpt',
        'content',
        'featured_image_url',
        'is_published',
        'published_at',
        'meta_title',
        'meta_description',
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
}
