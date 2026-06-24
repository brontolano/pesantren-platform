<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * gap-124 — "Pusat Pengaturan": satu halaman indeks yang menautkan SEMUA
 * halaman setting yang tersebar di berbagai grup nav, agar staf awam tak perlu
 * berburu menu. Murni navigasi: tiap kartu hanya tampil bila halaman tujuannya
 * BISA diakses user (canAccess) DAN route-nya terdaftar (getUrl tak melempar) —
 * jadi robust lintas tenant/mode/modul aktif. Tidak menyentuh logika/uang.
 */
class PusatPengaturan extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Pusat Pengaturan';

    protected static ?string $title = 'Pusat Pengaturan';

    protected static string|\UnitEnum|null $navigationGroup = 'Kelembagaan & Pengaturan';

    // Negatif agar muncul paling atas di grup Pengaturan (pintu masuk tunggal).
    protected static ?int $navigationSort = -10;

    protected static ?string $slug = 'pusat-pengaturan';

    protected string $view = 'filament.pages.pusat-pengaturan';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_tenant_settings') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('manage_tenant_settings') ?? false;
    }

    /**
     * Katalog kartu pengaturan dikelompokkan. Tiap entri:
     * [FQCN halaman, ikon heroicon, deskripsi awam].
     *
     * @return array<string, array<int, array{0: string, 1: string, 2: string}>>
     */
    protected function catalog(): array
    {
        return [
            'Identitas & Tampilan' => [
                ['App\\Filament\\Pages\\SetupPesantren', 'heroicon-o-building-library', 'Profil dasar pesantren: nama, alamat, kontak, kepala.'],
                ['App\\Filament\\Pages\\PengaturanBranding', 'heroicon-o-swatch', 'Logo, warna merek, dan info pendaftaran (PPDB).'],
                ['App\\Filament\\Pages\\PengaturanTampilan', 'heroicon-o-paint-brush', 'Tema warna & tata letak panel.'],
                ['App\\Filament\\Pages\\PengaturanIstilah', 'heroicon-o-language', 'Sesuaikan istilah (santri, ustadz, kelas, dll).'],
                ['App\\Filament\\Pages\\SetupUnit', 'heroicon-o-squares-2x2', 'Unit / lembaga & jenjang pendidikan.'],
            ],
            'Keuangan & Kebijakan' => [
                ['App\\Filament\\Pages\\PengaturanKeuangan', 'heroicon-o-banknotes', 'Parameter keuangan & pemisahan dana.'],
                ['App\\Filament\\Pages\\KebijakanPesantren', 'heroicon-o-scale', 'Kebijakan & parameter sistem pesantren.'],
            ],
            'Komunikasi & Aplikasi' => [
                ['App\\Filament\\Pages\\PengaturanNotifikasi', 'heroicon-o-bell', 'WhatsApp & email: template, broadcast, sakelar.'],
                ['App\\Filament\\Pages\\PengaturanAppSantri', 'heroicon-o-device-phone-mobile', 'Aplikasi santri: cara login & fitur.'],
                ['Modules\\AI\\Filament\\Pages\\PengaturanAi', 'heroicon-o-sparkles', 'Asisten AI Khadim Digital.'],
            ],
            'Modul, Integrasi & Data' => [
                ['App\\Filament\\Pages\\PusatModul', 'heroicon-o-puzzle-piece', 'Aktif / nonaktifkan modul pesantren.'],
                ['App\\Filament\\Pages\\IntegrasiApi', 'heroicon-o-link', 'Kunci API & integrasi layanan luar.'],
                ['App\\Filament\\Pages\\EksporEmis', 'heroicon-o-arrow-down-tray', 'Ekspor data untuk EMIS Kemenag.'],
                ['App\\Filament\\Pages\\CadanganPemulihan', 'heroicon-o-server-stack', 'Cadangan & pemulihan data tenant.'],
                ['App\\Filament\\Pages\\CadanganWebsite', 'heroicon-o-globe-alt', 'Cadangan konten website publik.'],
            ],
        ];
    }

    /**
     * Kelompok kartu siap-render — hanya halaman yang BISA diakses user &
     * route-nya valid. Aman dipanggil dari view.
     *
     * @return array<int, array{title: string, items: array<int, array{label: string, url: string, icon: string, description: string}>}>
     */
    public function getGroups(): array
    {
        $groups = [];

        foreach ($this->catalog() as $title => $items) {
            $cards = [];
            foreach ($items as [$class, $icon, $description]) {
                if (! class_exists($class)) {
                    continue;
                }

                try {
                    if (! $class::canAccess()) {
                        continue;
                    }
                    $url = $class::getUrl();
                } catch (\Throwable) {
                    // Route belum terdaftar (modul nonaktif / panel lain) → lewati.
                    continue;
                }

                $cards[] = [
                    'label' => $class::getNavigationLabel(),
                    'url' => $url,
                    'icon' => $icon,
                    'description' => $description,
                ];
            }

            if ($cards !== []) {
                $groups[] = ['title' => $title, 'items' => $cards];
            }
        }

        return $groups;
    }
}
