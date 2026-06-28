<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\PrayerTimesService;
use App\Services\QiblaService;
use App\Support\AppLauncher;
use App\Support\TenantBranding;
use Illuminate\Contracts\View\View;
use Modules\CMS\Models\Article;
use Modules\CMS\Services\TenantWebsiteService;

/**
 * Home publik "mode aplikasi" (1-app per pesantren). Dipakai oleh app Android
 * (WebView memuat /app) — tampilan ringkas ber-bottom-nav agar pengunjung tak
 * "lari" ke halaman marketing. Konten diambil dari CMS/branding yang sama
 * dengan landing, tapi tata letak app-native (Beranda · Ibadah · Kabar · Akun ·
 * Info). Defensif: kegagalan sumber apa pun → bagian itu kosong, bukan 500.
 */
class AppShellController extends Controller
{
    public function __invoke(TenantWebsiteService $websiteService): View
    {
        $web = [];
        try {
            $web = $websiteService->buildLandingPagePayload();
        } catch (\Throwable) {
            $web = [];
        }

        $branding = $web['branding'] ?? [];
        $contact = $web['contact'] ?? [];

        $user = auth()->user();
        $authedTarget = null;
        if ($user) {
            try {
                $authedTarget = AppLauncher::primaryDestination($user)['url'] ?? '/admin';
            } catch (\Throwable) {
                $authedTarget = '/admin';
            }
        }

        return view('app-shell', [
            'namaPesantren' => $branding['name'] ?? TenantBranding::name(),
            'tagline' => $branding['tagline'] ?? 'Pendidikan adab, ilmu, dan layanan santri',
            'logoUrl' => $branding['logo'] ?? TenantBranding::logoUrl(),
            'kontak' => [
                'alamat' => $contact['address'] ?? '',
                'telepon' => $contact['phone'] ?? '',
                'email' => $contact['email'] ?? '',
            ],
            'ibadah' => $this->ibadahPayload(),
            'artikel' => $this->artikelTerbaru(),
            'user' => $user,
            'authedTarget' => $authedTarget,
            'ppdbAktif' => $this->routeAda('tenant.ppdb.form'),
        ]);
    }

    /** Jadwal sholat + arah kiblat (selaras TenantLandingPageController). */
    private function ibadahPayload(): array
    {
        $lat = TenantBranding::latitude();
        $lng = TenantBranding::longitude();
        $adaLokasi = $lat !== 0.0 || $lng !== 0.0;

        $jadwal = [];
        $kiblat = null;
        $donasiUrl = null;

        if ($adaLokasi) {
            try {
                if ((bool) Setting::getTenantValue('beranda.tampil_jadwal_sholat', true)) {
                    $jadwal = app(PrayerTimesService::class)
                        ->fivePrayers($lat, $lng, TenantBranding::timezoneOffset());
                }
                if ((bool) Setting::getTenantValue('beranda.tampil_arah_kiblat', true)) {
                    $kiblat = (int) round(app(QiblaService::class)->bearing($lat, $lng));
                }
            } catch (\Throwable) {
                $jadwal = [];
                $kiblat = null;
            }
        }

        if ((bool) Setting::getTenantValue('beranda.tampil_donasi', true)) {
            $donasiUrl = '/donasi';
        }

        return ['jadwal' => $jadwal, 'kiblat' => $kiblat, 'donasiUrl' => $donasiUrl];
    }

    /** 6 kabar/artikel terbaru yang sudah terbit. */
    private function artikelTerbaru()
    {
        try {
            return Article::query()
                ->where('is_published', true)
                ->whereNotNull('published_at')
                ->latest('published_at')
                ->take(6)
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    private function routeAda(string $name): bool
    {
        return app('router')->has($name);
    }
}
