<?php

namespace Tests\Feature;

use Filament\Pages\Concerns\CanAuthorizeAccess;
use Filament\Pages\Page as FilamentPage;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Mengunci invarian "menu tampil TIDAK pernah 403" (anti 403-tile).
 *
 * Filament v5 core SUDAH menyaring navigasi via canAccess() untuk Resource &
 * Page (lihat vendor: Pages/Page.php::registerNavigationItems → "if (! canAccess()) return")
 * sehingga menu sidebar tak mungkin mengarah ke 403 — SELAMA tak ada yang:
 *
 *  (1) membuat custom Page TANPA override canAccess() → default trait
 *      CanAuthorizeAccess = TRUE → halaman tampil ke SEMUA peran (over-exposure
 *      + membingungkan staf yang fungsinya tak relevan), atau
 *  (2) meng-override registerNavigationItems() → mem-bypass gerbang canAccess core
 *      → bisa mendaftarkan item nav yang klik-nya 403.
 *
 * Dua test ini menjaga keduanya agar regresi di masa depan ketahuan saat CI,
 * bukan saat staf pesantren kebingungan menemui 403.
 */
class NavigasiTanpa403Test extends TestCase
{
    /**
     * Halaman yang SENGAJA pakai default canAccess (boleh diakses semua staf login).
     * TenantDashboard = beranda panel; bukan item nav fungsional terpisah.
     *
     * @var list<string>
     */
    private const ALLOWLIST_TANPA_CANACCESS = [
        \App\Filament\Pages\TenantDashboard::class,
    ];

    /**
     * @return list<string> FQCN setiap concrete custom Page (extends Filament\Pages\Page)
     */
    private function semuaCustomPage(): array
    {
        $dirs = array_merge(
            [base_path('app/Filament/Pages')],
            glob(base_path('Modules/*/app/Filament/Pages')) ?: [],
        );

        $classes = [];
        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iter as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $isi = file_get_contents($file->getPathname());
                if (! preg_match('/namespace\s+([^;]+);/', $isi, $ns) || ! preg_match('/\n\s*(?:final\s+|abstract\s+)?class\s+(\w+)/', $isi, $cl)) {
                    continue;
                }
                $fqcn = trim($ns[1]).'\\'.$cl[1];
                if (! class_exists($fqcn)) {
                    continue;
                }
                $ref = new ReflectionClass($fqcn);
                if ($ref->isAbstract() || ! $ref->isSubclassOf(FilamentPage::class)) {
                    continue;
                }
                $classes[] = $fqcn;
            }
        }

        return array_values(array_unique($classes));
    }

    public function test_setiap_custom_page_override_canAccess_agar_tak_tampil_ke_semua_peran(): void
    {
        $pelanggar = [];

        foreach ($this->semuaCustomPage() as $page) {
            if (in_array($page, self::ALLOWLIST_TANPA_CANACCESS, true)) {
                continue;
            }

            $deklarasi = (new ReflectionMethod($page, 'canAccess'))->getDeclaringClass()->getName();

            // canAccess HARUS dideklarasikan di kelas Page itu sendiri (atau
            // base page internal proyek) — BUKAN diwarisi dari trait Filament
            // CanAuthorizeAccess (yang default-nya return true untuk semua user).
            if ($deklarasi === CanAuthorizeAccess::class) {
                $pelanggar[] = $page;
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "Custom Page berikut TIDAK override canAccess() → default-nya tampil ke SEMUA peran (risiko menu di luar fungsi + membingungkan). "
            ."Tambahkan method canAccess() bergerbang izin, atau masukkan ke ALLOWLIST bila memang untuk semua staf:\n- "
            .implode("\n- ", $pelanggar),
        );
    }

    public function test_tak_ada_override_registerNavigationItems_yang_membypass_gerbang_canAccess(): void
    {
        $dirs = array_merge(
            [base_path('app/Filament')],
            glob(base_path('Modules/*/app/Filament')) ?: [],
        );

        $pelanggar = [];
        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iter as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $isi = file_get_contents($file->getPathname());
                // registerNavigationItems() adalah method core yang memuat gerbang
                // "if (! canAccess()) return". Meng-override-nya bisa menghapus gerbang itu.
                if (preg_match('/function\s+registerNavigationItems\s*\(/', $isi)) {
                    $pelanggar[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "File berikut meng-override registerNavigationItems() → berpotensi mem-bypass gerbang canAccess() Filament core (sumber 403-tile). "
            ."Hapus override, atau pastikan tetap memanggil `if (! static::canAccess()) return;`:\n- "
            .implode("\n- ", $pelanggar),
        );
    }
}
