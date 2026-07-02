<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\PrayerTimesService;
use App\Services\QiblaService;
use App\Support\TenantBranding;
use App\Support\TenantCustomizationRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Modules\CMS\Models\Article;
use Modules\CMS\Services\TenantWebsiteService;

/**
 * Home publik "mode aplikasi" (1-app per pesantren). Dipakai app Android
 * (WebView memuat /beranda) — tampilan ringkas ber-bottom-nav agar pengunjung
 * tak "lari" ke halaman marketing.
 *
 * Konten (menu profil, galeri IG, Asy-Syifaa TV, jadwal kajian, donasi, zakat/
 * wakaf) DAPAT DIATUR di admin (PengaturanBranding) lewat Setting; default di
 * sini memakai tautan resmi asy-syifaa.com. Defensif: gagal apa pun → bagian
 * kosong, bukan 500.
 */
class AppShellController extends Controller
{
    /*
     * Default tautan konten (bila admin belum mengisi Setting) kini dari
     * registry: TenantCustomizationRegistry::appContentLinks() — satu sumber
     * dengan PengaturanBranding (tahap 4 #343, dulu konstanta DEFAULTS duplikat).
     */

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
            'tanggal' => $this->tanggalIbadah(),
            'cfg' => $this->appConfig(),
            'artikel' => $this->artikelTerbaru(),
            'masukUrl' => '/masuk',
        ]);
    }

    /**
     * Manifest PWA — agar "Tambahkan ke Layar Utama" (iPhone/Android) memasang
     * ikon logo pesantren, nama, dan jalan layar-penuh (standalone).
     */
    public function manifest(TenantWebsiteService $websiteService): JsonResponse
    {
        $nama = TenantBranding::name();
        try {
            $nama = ($websiteService->buildLandingPagePayload()['branding']['name'] ?? null) ?: $nama;
        } catch (\Throwable) {
        }
        $logo = TenantBranding::logoUrl();
        $icons = $logo ? [
            ['src' => $logo, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => $logo, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ] : [];

        return response()->json([
            'name' => $nama,
            'short_name' => mb_substr($nama, 0, 12),
            'start_url' => '/beranda',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#fffaf3',
            'theme_color' => '#0F5132',
            'icons' => $icons,
        ])->header('Content-Type', 'application/manifest+json');
    }

    /** Konfigurasi konten app (Setting → fallback default asy-syifaa). */
    private function appConfig(): array
    {
        $cfg = [];
        foreach (TenantCustomizationRegistry::appContentLinks() as $key => $default) {
            try {
                $val = Setting::getTenantStringValue($key, null);
            } catch (\Throwable) {
                $val = null;
            }
            $cfg[$key] = ($val !== null && trim($val) !== '') ? trim($val) : $default;
        }

        return $cfg;
    }

    /** Tanggal hari ini dalam Masehi & Hijriah (untuk header jadwal sholat). */
    private function tanggalIbadah(): array
    {
        $masehi = now()->locale('id')->translatedFormat('l, d F Y');

        $hijriah = '';
        try {
            if (extension_loaded('intl')) {
                $cal = \IntlCalendar::createInstance(null, 'en@calendar=islamic-civil');
                $cal->setTime(now()->timestamp * 1000);
                $d = $cal->get(\IntlCalendar::FIELD_DAY_OF_MONTH);
                $mo = $cal->get(\IntlCalendar::FIELD_MONTH); // 0-based
                $y = $cal->get(\IntlCalendar::FIELD_YEAR);
                $bulan = ['Muharram', 'Safar', 'Rabiul Awal', 'Rabiul Akhir', 'Jumadil Awal',
                    'Jumadil Akhir', 'Rajab', "Sya'ban", 'Ramadhan', 'Syawal', "Dzulqa'dah", 'Dzulhijjah'];
                $hijriah = $d.' '.($bulan[$mo] ?? '').' '.$y.' H';
            }
        } catch (\Throwable) {
            $hijriah = '';
        }

        return ['masehi' => $masehi, 'hijriah' => $hijriah];
    }

    /** Jadwal sholat + arah kiblat (selaras TenantLandingPageController). */
    private function ibadahPayload(): array
    {
        $lat = TenantBranding::latitude();
        $lng = TenantBranding::longitude();
        $adaLokasi = $lat !== 0.0 || $lng !== 0.0;

        $jadwal = [];
        $kiblat = null;

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

        return ['jadwal' => $jadwal, 'kiblat' => $kiblat];
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
}
