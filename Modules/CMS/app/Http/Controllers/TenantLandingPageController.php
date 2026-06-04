<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CMS\Models\Article;
use Modules\CMS\Services\TenantWebsiteService;

class TenantLandingPageController extends Controller
{
    public function __invoke(TenantWebsiteService $websiteService): Response
    {
        $website = $websiteService->buildLandingPagePayload();

        return Inertia::render('CMS/LandingPage', [
            'tenant' => tenant(),
            'website' => $website,
            'blog' => [
                'indexUrl' => '/#blog',
                'hasPublishedArticles' => Article::query()
                    ->where('is_published', true)
                    ->whereNotNull('published_at')
                    ->exists(),
            ],
        ]);
    }
}
