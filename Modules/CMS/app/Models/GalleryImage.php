<?php

namespace Modules\CMS\Models;

use App\Services\MediaRegistrar;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryImage extends Model
{
    use BelongsToTenant;

    protected $table = 'gallery_images';

    protected $fillable = [
        'tenant_id',
        'gallery_id',
        'image_url',
        'caption',
        'sort_order',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Foto yang diupload (path disk) otomatis ter-index ke Media File,
        // dikelompokkan per galeri. URL eksternal dilewati oleh MediaRegistrar.
        static::saved(function (GalleryImage $img): void {
            if (! $img->image_url) {
                return;
            }
            $title = $img->relationLoaded('gallery')
                ? optional($img->gallery)->title
                : optional($img->gallery()->first())->title;
            app(MediaRegistrar::class)
                ->register($img->image_url, $title ? 'Galeri/'.$title : 'Galeri');
        });
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class, 'gallery_id');
    }

    /** URL foto absolut (URL penuh apa adanya, path disk → Storage::url). */
    public function getUrlAttribute(): ?string
    {
        return Gallery::resolveMediaUrl($this->image_url);
    }
}
