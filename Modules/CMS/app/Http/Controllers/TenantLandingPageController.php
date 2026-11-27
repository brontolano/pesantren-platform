<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\PrayerTimesService;
use App\Services\QiblaService;
use App\Support\TenantBranding;
use App\Support\TenantCustomizationRegistry;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CMS\Models\Article;
use Modules\CMS\Models\WebsiteSetting;
use Modules\CMS\Services\TenantWebsiteService;

class TenantLandingPageController extends Controller
{
    public function __invoke(TenantWebsiteService $websiteService): Response
    {
        // Tenant dengan landing custom dirender komponen sendiri agar LandingPage
        // default tetap bersih & tenant lain tidak terdampak perubahan custom.
        // Pemilihan komponen via registry deklaratif (Refs #343) — entri albakids
        // → 'CMS/AlbaKidsLandingV2' (V2 dipromosikan dari /v2 jadi halaman utama),
        // tenant lain → default 'CMS/LandingPage'. Lihat config/tenant-customization.php.
        $component = TenantCustomizationRegistry::landingComponent();

        return Inertia::render($component, $this->payload($websiteService));
    }

    public function preview(string $tenantKey, TenantWebsiteService $websiteService): Response
    {
        abort_unless((string) tenant()?->getTenantKey() === $tenantKey, 404);

        $draft = WebsiteSetting::getTenantValue('website_builder_draft');
        abort_unless(is_array($draft) && is_array($draft['sections'] ?? null), 404);

        return Inertia::render(
            TenantCustomizationRegistry::landingComponent(),
            $this->payload($websiteService, $draft, previewMode: true)
        );
    }

    /** Props bersama landing (kontrak komponen: tenant, website, blog, ibadah). */
    private function payload(TenantWebsiteService $websiteService, ?array $draft = null, bool $previewMode = false): array
    {
        $website = $websiteService->buildLandingPagePayload($draft);
        if ($previewMode) {
            $website['seo']['robots'] = 'noindex,nofollow';
        }

        return [
            'tenant' => tenant(),
            'website' => $website,
            'previewMode' => $previewMode,
            'blog' => [
                'indexUrl' => '/#blog',
                'hasPublishedArticles' => Article::query()
                    ->where('is_published', true)
                    ->whereNotNull('published_at')
                    ->exists(),
            ],
            ...$this->ibadahPayload(),
            // Etalase demo: tombol "Coba Demo" hanya muncul di tenant demo + flag ON.
            'demoPickerUrl' => (config('demo.autologin_enabled')
                && tenant()?->getTenantKey() === config('demo.autologin_tenant'))
                ? '/coba'
                : null,
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
        $adaLokasi = $lat !== 0.0 || $lng !== 0.0;

        $jadwalSholat = [];
        $arahKiblat = null;

        if ($adaLokasi) {
            try {
                if ((bool) Setting::getTenantValue('beranda.tampil_jadwal_sholat', true)) {
                    $jadwalSholat = app(PrayerTimesService::class)
                        ->fivePrayers($lat, $lng, TenantBranding::timezoneOffset());
                }
                if ((bool) Setting::getTenantValue('beranda.tampil_arah_kiblat', true)) {
                    $arahKiblat = (int) round(app(QiblaService::class)->bearing($lat, $lng));
                }
            } catch (\Throwable $e) {
                $jadwalSholat = [];
                $arahKiblat = null;
            }
        }

        $tampilDonasi = (bool) Setting::getTenantValue('beranda.tampil_donasi', true);

        return [
            'jadwalSholat' => $jadwalSholat,
            'arahKiblat' => $arahKiblat,
            'donasiUrl' => $tampilDonasi ? '/donasi' : null,
        ];
    }
}
