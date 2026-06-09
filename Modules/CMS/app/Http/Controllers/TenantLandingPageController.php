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
        // Landing utama Alba Kids = V2 (dipromosikan dari /v2 menjadi halaman utama).
        $component = AlbaKidsTenant::isActive() ? 'CMS/AlbaKidsLandingV2' : 'CMS/LandingPage';

        return Inertia::render($component, $this->payload($websiteService));
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
