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
        // Tenant dengan landing custom dirender komponen sendiri agar LandingPage
        // default tetap bersih & tenant lain tidak terdampak perubahan custom.
        $component = AlbaKidsTenant::isActive() ? 'CMS/AlbaKidsLanding' : 'CMS/LandingPage';

        return Inertia::render($component, $this->payload($websiteService));
    }

    /**
     * Landing AlbaKids VERSI 2 di /v2 — permanen di git (pengganti versi
     * container-only yang hilang saat rebuild). Hanya untuk tenant AlbaKids.
     */
    public function v2(TenantWebsiteService $websiteService): Response
    {
        abort_unless(AlbaKidsTenant::isActive(), 404);

        return Inertia::render('CMS/AlbaKidsLandingV2', $this->payload($websiteService));
    }

    /** Props bersama landing (kontrak komponen: tenant, website, blog). */
    private function payload(TenantWebsiteService $websiteService): array
    {
        return [
            'tenant' => tenant(),
            'website' => $websiteService->buildLandingPagePayload(),
            'blog' => [
                'indexUrl' => '/#blog',
                'hasPublishedArticles' => Article::query()
                    ->where('is_published', true)
                    ->whereNotNull('published_at')
                    ->exists(),
            ],
        ];
    }
}
