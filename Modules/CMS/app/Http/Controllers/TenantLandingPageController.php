<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\PrayerTimesService;
use App\Services\QiblaService;
use App\Support\AlbaKidsTenant;
use App\Support\TenantBranding;
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

    /** Props bersama landing (kontrak komponen: tenant, website, blog, ibadah). */
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
            ...$this->ibadahPayload(),
        ];
    }

    /**
     * Konten publik ibadah (1-aplikasi): jadwal sholat + arah kiblat (bila lokasi
     * tenant di-set) + tautan donasi. Defensif: kegagalan apa pun → kosong, bukan
     * 500. (CMS toggle per-fitur menyusul — lihat blueprint 1-aplikasi.)
     *
     * @return array{jadwalSholat: list<array>, arahKiblat: int|null, donasiUrl: string}
     */
    private function ibadahPayload(): array
    {
        $lat = TenantBranding::latitude();
        $lng = TenantBranding::longitude();

        $jadwalSholat = [];
        $arahKiblat = null;

        if ($lat !== 0.0 || $lng !== 0.0) {
            try {
                $jadwalSholat = app(PrayerTimesService::class)
                    ->fivePrayers($lat, $lng, TenantBranding::timezoneOffset());
                $arahKiblat = (int) round(app(QiblaService::class)->bearing($lat, $lng));
            } catch (\Throwable $e) {
                $jadwalSholat = [];
                $arahKiblat = null;
            }
        }

        return [
            'jadwalSholat' => $jadwalSholat,
            'arahKiblat' => $arahKiblat,
            'donasiUrl' => '/donasi',
        ];
    }
}
