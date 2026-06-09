<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CMS\Models\Article;
use Modules\CMS\Services\TenantWebsiteService;

class ArticleDetailController extends Controller
{
    public function __invoke(Request $request, string $slug, TenantWebsiteService $websiteService): Response
    {
        $article = Article::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->firstOrFail();

        $website = $websiteService->buildLandingPagePayload();

        return Inertia::render('CMS/ArticleDetail', [
            'tenant' => tenant(),
            'website' => $website,
            'article' => [
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'content' => $article->content,
                'featuredImageUrl' => $article->featured_image_url,
                'publishedAt' => optional($article->published_at)?->toDateString(),
                'metaTitle' => $article->meta_title,
                'metaDescription' => $article->meta_description,
                'url' => $request->url(),
            ],
            'seo' => $websiteService->buildArticleSeoPayload($article),
            'homeUrl' => '/',
        ]);
    }
}
