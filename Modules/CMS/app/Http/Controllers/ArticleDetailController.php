<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CMS\Models\Article;
use Modules\CMS\Models\Comment;
use Modules\CMS\Services\TenantWebsiteService;
use Modules\CMS\Support\ArticleContentRenderer;

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
                // HTML aman (blok Video Kajian → iframe host-terkunci; sisanya
                // disanitasi Symfony). Vue merender ini via v-html; 'content'
                // mentah dipertahankan untuk kompat mundur.
                'contentHtml' => ArticleContentRenderer::render($article->content),
                'featuredImageUrl' => $article->featured_image_url,
                'publishedAt' => optional($article->published_at)?->toDateString(),
                'metaTitle' => $article->meta_title,
                'metaDescription' => $article->meta_description,
                'url' => $request->url(),
            ],
            'seo' => $websiteService->buildArticleSeoPayload($article),
            'homeUrl' => '/',
            // 1-aplikasi (Fase 2c) — komentar publik (approved saja). Body
            // di-escape oleh Vue ({{ }}) — JANGAN v-html. Guard Schema::hasTable:
            // bila deploy mendahului `tenants:migrate` di tenant lama, halaman
            // artikel TETAP tampil (komentar kosong) — anti 500 (pelajaran PPDB).
            'comments' => Schema::hasTable('comments')
                ? Comment::query()
                    ->where('article_id', $article->id)
                    ->approved()
                    ->orderByDesc('created_at')
                    ->get()
                    ->map(fn (Comment $c): array => [
                        'id' => $c->id,
                        'author' => (string) $c->author_name,
                        'body' => (string) $c->body,
                        'createdAt' => optional($c->created_at)?->toDateString(),
                    ])->all()
                : [],
            'comment' => [
                'isLoggedIn' => $request->user() !== null,
                'authorName' => $request->user()?->name,
                'storeUrl' => '/artikel/'.$article->slug.'/komentar',
                'loginUrl' => '/jemaah/login',
            ],
        ]);
    }
}
