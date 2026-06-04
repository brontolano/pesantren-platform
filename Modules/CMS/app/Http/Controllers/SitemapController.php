<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Modules\CMS\Models\Article;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([
            [
                'loc' => url('/'),
                'lastmod' => now()->toDateString(),
            ],
            [
                'loc' => url('/ppdb'),
                'lastmod' => now()->toDateString(),
            ],
        ])->merge(
            Article::query()
                ->where('is_published', true)
                ->whereNotNull('published_at')
                ->orderByDesc('published_at')
                ->get()
                ->map(fn (Article $article): array => [
                    'loc' => url('/artikel/'.$article->slug),
                    'lastmod' => optional($article->updated_at ?? $article->published_at)?->toDateString(),
                ])
        );

        $xml = view('cms::sitemap', [
            'urls' => $urls,
        ])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
