<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\PrayerTimesService;
use App\Services\QiblaService;
use App\Support\TenantBranding;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    /**
     * Versi build app-shell. Dinaikkan manual saat mengubah tampilan agar
     * pengguna bisa memastikan sudah menerima versi baru (mengatasi kebingungan
     * cache: countdown dihitung JS jadi tetap jalan walau HTML lama). Ditampilkan
     * kecil di footer Beranda.
     */
    private const APP_SHELL_BUILD = '2026.07.06-4';

    public function __invoke(TenantWebsiteService $websiteService): \Illuminate\Http\Response
    {
        $web = [];
        try {
            $web = $websiteService->buildLandingPagePayload();
        } catch (\Throwable) {
            $web = [];
        }

        $branding = $web['branding'] ?? [];
        $contact = $web['contact'] ?? [];

        $view = view('app-shell', [
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
            'appBuild' => self::APP_SHELL_BUILD,
        ]);

        // Paksa selalu ambil-ulang: app-shell = HTML+CSS inline yang sering
        // diubah; no-store mencegah browser/TWA menyajikan salinan basi.
        return response($view)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Beranda Staf (PWA) — "panel admin dirapikan jadi PWA". Menampilkan ikon
     * menu HANYA yang sesuai izin peran pengguna; tiap ikon membuka halaman
     * panel (/admin/...) yang sudah responsif. Untuk staf yang belum punya PWA
     * khusus (keamanan, UKS, TU, konselor, dll).
     */
    public function stafHome(Request $request): View|RedirectResponse
    {
        $u = auth()->user();
        if (! $u) {
            return redirect('/masuk');
        }

        // Desktop browser → panel Filament langsung (lebih nyaman di layar besar).
        // Mobile / PWA → tetap di halaman ini (ikon launcher native feel).
        if (! $this->isMobileRequest($request)) {
            return redirect('/admin');
        }

        // Katalog menu: [label, ikon, izin[], url]. Tampil bila user canAny(izin).
        // Nama grup DISAMAKAN PERSIS dengan navigationGroups([...]) panel admin
        // (TenantPanelProvider::navigationGroups) — "logic seperti panel admin":
        // staf melihat pengelompokan yang sama persis dengan yang admin lihat,
        // bedanya hanya diringkas jadi kartu PWA per izin yang ia miliki.
        $katalog = [
            'Santri' => [
                ['Data Santri', 'users', ['view_santri', 'manage_santri'], '/admin/santri/santris'],
                ['Alumni', 'user', ['view_alumni', 'manage_alumni'], '/admin/alumni/alumnis'],
            ],
            'Master Akademik' => [
                ['Kelas', 'grid', ['view_akademik', 'manage_akademik', 'manage_santri'], '/admin/kelas'],
            ],
            'Perizinan & Kunjungan' => [
                ['Izin Santri', 'doc', ['view_kegiatan', 'manage_kegiatan'], '/admin/izin-santris'],
                ['Kunjungan Wali', 'users', ['view_kegiatan', 'manage_kegiatan'], '/admin/kunjungan-walis'],
                ['Pengajuan Jenguk', 'bell', ['view_kegiatan', 'manage_kegiatan'], '/admin/pengajuan-jenguk'],
            ],
            'Kegiatan' => [
                ['Log Gerbang', 'shield', ['view_kegiatan', 'manage_kegiatan'], '/admin/log-gerbang'],
                ['Kegiatan', 'cal', ['view_kegiatan', 'manage_kegiatan'], '/admin/kegiatans'],
            ],
            'Kedisiplinan' => [
                ['Pelanggaran / Ta\'zir', 'shield', ['view_kedisiplinan', 'manage_kedisiplinan'], '/admin/pelanggaran-santri/pelanggaran-santris'],
            ],
            'Asrama & Pengasuhan' => [
                ['Blok Asrama', 'home', ['view_asrama', 'manage_asrama'], '/admin/blok-asrama/blok-asramas'],
                ['Kamar', 'home', ['view_asrama', 'manage_asrama'], '/admin/kamar-asrama/kamar-asramas'],
                ['Penempatan Asrama', 'home', ['view_asrama', 'manage_asrama'], '/admin/penempatan-asrama/penempatan-asramas'],
                ['Presensi Asrama', 'check', ['view_asrama', 'manage_asrama'], '/admin/kepengasuhan/presensi-asrama/presensi-asramas'],
            ],
            'Kesehatan' => [
                ['Kunjungan UKS', 'heart', ['view_kesehatan', 'manage_kesehatan'], '/admin/kesehatan/kunjungan-uks'],
                ['Riwayat Kesehatan', 'heart', ['view_kesehatan', 'manage_kesehatan'], '/admin/riwayat-kesehatans'],
                ['Obat', 'heart', ['view_kesehatan', 'manage_kesehatan'], '/admin/kesehatan/obat'],
            ],
            'Konseling' => [
                ['Konseling', 'chat', ['view_konseling', 'manage_konseling'], '/admin/sesi-konselings'],
            ],
            'Pembelajaran' => [
                ['Presensi Santri', 'check', ['view_akademik', 'manage_akademik'], '/admin/presensi-santri/presensi-santris'],
                ['Presensi Pengajar', 'check', ['run_payroll', 'view_akademik'], '/admin/presensi-staf/presensi-stafs'],
                ['Rekap Jurnal KBM', 'book', ['view_akademik', 'manage_akademik'], '/admin/rekap-jurnal-kbm'],
            ],
            'Pendaftaran' => [
                ['Calon Santri', 'user', ['view_ppdb', 'manage_ppdb'], '/admin/ppdb/calon-santri'],
                ['Hasil Ujian', 'doc', ['view_ppdb', 'manage_ppdb'], '/admin/ppdb/hasil-ujian'],
            ],
            'Tagihan Santri' => [
                ['Pembayaran', 'wallet', ['view_tagihan', 'manage_tagihan'], '/admin/pembayaran/pembayarans'],
            ],
            'Laporan Keuangan' => [
                ['Laporan Keuangan', 'chart', ['view_laporan_keuangan'], '/admin/laporan-keuangan'],
            ],
            'Buku Besar' => [
                ['Jurnal', 'book', ['view_ledger', 'manage_ledger'], '/admin/jurnals'],
            ],
            'Penggajian' => [
                ['Slip Gaji', 'wallet', ['run_payroll'], '/admin/payroll/slip'],
            ],
            'Komunikasi' => [
                ['Broadcast WA', 'bell', ['manage_notifikasi'], '/admin/broadcast-wa/broadcast-was'],
            ],
            'Pengaturan Sistem' => [
                ['Pengguna', 'users', ['manage_users'], '/admin/users'],
                ['Settings & Branding', 'cog', ['manage_tenant_settings'], '/admin/settings'],
            ],
        ];

        $groups = [];
        foreach ($katalog as $judul => $items) {
            $vis = array_values(array_filter($items, fn ($it) => $u->canAny($it[2])));
            if ($vis !== []) {
                $groups[$judul] = $vis;
            }
        }

        $jam = (int) now()->format('H');
        $sapa = $jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam'));

        // Pintasan Cepat — item pertama dari maksimal 4 grup pertama yg terlihat
        // untuk peran (bukan menu palsu; benar2 tautan yg user berhak akses).
        $pintasan = [];
        foreach (array_slice($groups, 0, 4, true) as $items) {
            if (isset($items[0])) {
                $pintasan[] = $items[0]; // [label, ikon, izin[], url]
            }
        }

        // Jadwal 5 waktu sholat + tandai yg berikutnya (real, helper yg sudah ada).
        $ibadah = $this->ibadahPayload();
        $sholat = [];
        try {
            $jadwal = $ibadah['jadwal'] ?? [];
            if ($jadwal !== []) {
                $nextKey = app(PrayerTimesService::class)->nextPrayerKey($jadwal);
                foreach ($jadwal as $p) {
                    $sholat[] = [
                        'label' => $p['label'] ?? '',
                        'time' => $p['time'] ?? '',
                        'next' => (($p['key'] ?? null) === $nextKey),
                    ];
                }
            }
        } catch (\Throwable) {
            $sholat = [];
        }

        // Jumlah fitur (menu) yg boleh diakses peran — untuk kartu profil.
        $totalFitur = 0;
        foreach ($groups as $items) {
            $totalFitur += count($items);
        }

        return view('staf-home', [
            'namaPesantren' => TenantBranding::name(),
            'logoUrl' => TenantBranding::logoUrl(),
            'user' => $u,
            'peran' => $u->getRoleNames()->map(fn ($r) => str()->headline($r))->implode(' · '),
            'sapa' => $sapa,
            'groups' => $groups,
            'pintasan' => $pintasan,
            'tanggal' => $this->tanggalIbadah(),
            'sholat' => $sholat,
            'totalFitur' => $totalFitur,
            'pengumuman' => $this->artikelTerbaru(),
            'adminUrl' => '/admin',
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

    /** Deteksi apakah request dari perangkat mobile / PWA (bukan desktop). */
    private function isMobileRequest(Request $request): bool
    {
        // Jika sudah dalam mode standalone PWA, selalu anggap mobile.
        if ($request->header('Sec-Fetch-Dest') === 'document'
            && str_contains((string) $request->header('Referer', ''), 'android')) {
            return true;
        }

        $ua = strtolower((string) $request->header('User-Agent', ''));

        return (bool) preg_match(
            '/mobile|android|iphone|ipad|ipod|blackberry|iemobile|opera mini|webos|windows phone/i',
            $ua
        );
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
