<?php

namespace Modules\CMS\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

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

    protected static function booted(): void
    {
        // Cover yang diupload (path disk) otomatis ter-index ke Media File.
        static::saved(function (Gallery $g): void {
            if ($g->cover_image_url) {
                app(\App\Services\MediaRegistrar::class)
                    ->register($g->cover_image_url, 'Galeri/'.$g->title);
            }
        });
    }

    public function images(): HasMany
    {
        return $this->hasMany(GalleryImage::class, 'gallery_id')->orderBy('sort_order');
    }

    /**
     * URL cover absolut untuk konsumsi publik (API/website) & thumbnail admin.
     * - Nilai berupa URL penuh (http...) dipakai apa adanya (mis. data migrasi).
     * - Nilai berupa path disk tenant dikonversi via Storage::url (mis. upload admin).
     * - Bila cover kosong, pakai foto pertama sebagai fallback.
     */
    public function getCoverUrlAttribute(): ?string
    {
        $value = $this->cover_image_url;
        if (! $value) {
            $value = $this->relationLoaded('images')
                ? optional($this->images->first())->image_url
                : optional($this->images()->first())->image_url;
        }

        return self::resolveMediaUrl($value);
    }

    /** Konversi path disk → URL absolut; biarkan URL penuh apa adanya. */
    public static function resolveMediaUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return Storage::disk(config('media.disk', 'public'))->url(ltrim($value, '/'));
    }
}
