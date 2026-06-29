<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\PrayerTimesService;
use App\Services\QiblaService;
use App\Support\TenantBranding;
use Illuminate\Contracts\View\View;
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
    /** Default tautan konten (dipakai bila admin belum mengisi Setting). */
    private const DEFAULTS = [
        'app_profil_pondok_url' => 'https://asy-syifaa.com/profil-pondok-pesantren',
        'app_profil_abuya_url' => 'https://asy-syifaa.com/profil-abuya',
        'app_profil_guru_url' => 'https://asy-syifaa.com/profil-guru',
        'app_struktur_url' => 'https://asy-syifaa.com/struktur-organisasi',
        'app_instagram_url' => 'https://www.instagram.com/ponpes.asysyifaa',
        'app_instagram2_url' => 'https://www.instagram.com/asysyifaapusat/',
        'app_instagram3_url' => 'https://www.instagram.com/syifaalawiyyahofficial/',
        'app_youtube_url' => 'https://www.youtube.com/@AsySyifaaTVOfficial/videos',
        'app_facebook_url' => 'https://www.facebook.com/AsySyifaaTV/live_videos',
        'app_jadwal_kajian_url' => '',
        'app_donasi_url' => '/donasi',
        'app_zakat_wakaf_url' => '',
    ];

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

    /** Konfigurasi konten app (Setting → fallback default asy-syifaa). */
    private function appConfig(): array
    {
        $cfg = [];
        foreach (self::DEFAULTS as $key => $default) {
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
