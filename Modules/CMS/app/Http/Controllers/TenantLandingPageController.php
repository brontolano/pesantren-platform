<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\AlbaKidsTenant;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CMS\Models\Article;
use Modules\CMS\Services\TenantWebsiteService;

class TenantLandingPageController extends Controller
{
    public function __invoke(TenantWebsiteService $websiteService): Response
    {
        $website = $websiteService->buildLandingPagePayload();

        // Tenant dengan landing custom dirender komponen sendiri agar LandingPage
        // default tetap bersih & tenant lain tidak terdampak perubahan custom.
        $component = AlbaKidsTenant::isActive() ? 'CMS/AlbaKidsLanding' : 'CMS/LandingPage';

        return Inertia::render($component, [
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
