<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CMS\Models\Article;
use Modules\CMS\Models\Gallery;
use Modules\CMS\Models\WebsiteSection;
use Modules\CMS\Models\WebsiteSetting;

/**
 * API publik konten CMS untuk website tenant (mis. asy-syifaa.com).
 * Tenant di-resolve dari domain (api.<tenant>) oleh middleware tenancy web.
 * Bentuk respons mengikuti kontrak docs/SPEC-API-WEBSITE-ASY-SYIFAA.md:
 *   { "ok": true, "data": ... }
 */
class CmsPublicApiController extends Controller
{
    private function hasTenant(): bool
    {
        return tenancy()->initialized && tenant() !== null;
    }

    /** GET /api/v1/posts?category=&limit= */
    public function posts(Request $request): JsonResponse
    {
        if (! $this->hasTenant()) {
            return response()->json(['ok' => true, 'data' => []]);
        }

        $limit = min(50, max(1, (int) $request->query('limit', 12)));
        $category = $request->query('category');

        $query = Article::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now()) // penjadwalan: belum tampil sebelum waktunya
            ->orderByDesc('published_at');

        if (is_string($category) && $category !== '') {
            $query->where('category', $category);
        }

        $data = $query->limit($limit)->get()->map(fn (Article $a) => $this->mapPost($a))->all();

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /** GET /api/v1/posts/{slug} */
    public function post(string $slug): JsonResponse
    {
        if (! $this->hasTenant()) {
            return response()->json(['ok' => false, 'message' => 'Tenant tidak ditemukan'], 404);
        }

        $article = Article::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->where('published_at', '<=', now())
            ->first();

        if (! $article) {
            return response()->json(['ok' => false, 'message' => 'Artikel tidak ditemukan'], 404);
        }

        return response()->json(['ok' => true, 'data' => $this->mapPost($article)]);
    }

    /** GET /api/public/content/profile — section profil website (Tentang/Visi/Misi/dll) */
    public function profile(): JsonResponse
    {
        if (! $this->hasTenant()) {
            return response()->json(['ok' => true, 'data' => []]);
        }

        $data = WebsiteSection::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (WebsiteSection $s) => [
                'type' => $s->section_type,
                'sort' => (int) $s->sort_order,
                'config' => $s->config ?? [],
            ])
            ->all();

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /** GET /api/public/content/settings — pengaturan website (key → value) */
    public function settings(): JsonResponse
    {
        if (! $this->hasTenant()) {
            return response()->json(['ok' => true, 'data' => (object) []]);
        }

        $map = WebsiteSetting::query()
            ->get()
            ->mapWithKeys(fn (WebsiteSetting $s) => [$s->key => $s->value])
            ->all();

        return response()->json(['ok' => true, 'data' => empty($map) ? (object) [] : $map]);
    }

    /** GET /api/public/content/gallery?limit= */
    public function gallery(Request $request): JsonResponse
    {
        if (! $this->hasTenant()) {
            return response()->json(['ok' => true, 'data' => []]);
        }

        $limit = min(200, max(1, (int) $request->query('limit', 200)));

        $data = Gallery::query()
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (Gallery $g) => $this->mapGallery($g))
            ->all();

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /** GET /api/public/content/gallery/{slug} */
    public function galleryItem(string $slug): JsonResponse
    {
        if (! $this->hasTenant()) {
            return response()->json(['ok' => false, 'message' => 'Tenant tidak ditemukan'], 404);
        }

        $gallery = Gallery::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with('images')
            ->first();

        if (! $gallery) {
            return response()->json(['ok' => false, 'message' => 'Galeri tidak ditemukan'], 404);
        }

        $data = $this->mapGallery($gallery);
        $data['images'] = $gallery->images->map(fn ($img) => [
            'url' => $img->url,
            'caption' => $img->caption,
        ])->all();

        return response()->json(['ok' => true, 'data' => $data]);
    }

    private function mapPost(Article $a): array
    {
        return [
            'id' => (string) $a->id,
            'slug' => $a->slug,
            'title' => $a->title,
            'excerpt' => $a->excerpt,
            'content' => $a->content,
            'content_html' => $a->content,
            'featured_image' => $a->featured_image_url,
            'published_at' => optional($a->published_at)?->toIso8601String(),
            'created_at' => optional($a->created_at)?->toIso8601String(),
            'category' => ['name' => $a->category ?? 'article'],
            'author' => ['full_name' => null],
            'tags' => [],
        ];
    }

    private function mapGallery(Gallery $g): array
    {
        return [
            'id' => (string) $g->id,
            'slug' => $g->slug,
            'title' => $g->title,
            'description' => $g->description,
            'category' => null,
            'date' => optional($g->published_at)?->toDateString(),
            'cover_url' => $g->cover_url,
            'folder_name' => $g->slug,
        ];
    }
}
