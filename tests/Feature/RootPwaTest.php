<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * PWA scope ROOT "/" (1-aplikasi, fondasi TWA Fase 4): manifest + ikon agar
 * SELURUH aplikasi (Beranda) installable. Pasif (JSON/SVG), per-tenant branding.
 */
class RootPwaTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'tenant_storage', 'settings', 'users', 'roles', 'permissions', 'model_has_roles',
        ]);
        $this->truncatePpdbTables(['settings', 'domains', 'tenants', 'tenant_storage']);
    }

    /** @return array{0: Tenant, 1: string} */
    private function boot(string $prefix): array
    {
        $host = $prefix.'-'.Str::lower(Str::random(6)).'.localhost';
        $tenantId = explode('.', $host)[0];
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Root', $host);

        return [$tenant, $host];
    }

    /** @return array{HTTP_HOST: string, SERVER_NAME: string} */
    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_manifest_root_tersedia_scope_root(): void
    {
        [, $host] = $this->boot('mf');

        $resp = $this->get("http://{$host}/manifest.webmanifest", $this->headers($host));

        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'application/manifest+json');
        $resp->assertJson([
            'id' => '/',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
        ]);
        $resp->assertJsonStructure([
            'name', 'short_name', 'theme_color', 'background_color',
            'icons' => [['src', 'sizes', 'type', 'purpose']],
        ]);
    }

    public function test_icon_root_render_svg(): void
    {
        [, $host] = $this->boot('ic');

        $resp = $this->get("http://{$host}/icon.svg?size=512", $this->headers($host));

        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $resp->getContent());
    }

    private function setRootSwFlag(Tenant $tenant, bool $enabled): void
    {
        tenancy()->initialize($tenant);
        Setting::updateOrCreate(
            ['tenant_id' => $tenant->id, 'key' => 'pwa.root_sw_enabled'],
            ['value' => $enabled],
        );
        tenancy()->end();
    }

    public function test_service_worker_flag_off_mengembalikan_self_destruct(): void
    {
        // Default flag OFF → /sw.js WAJIB berupa kill-switch: unregister diri,
        // hapus cache 'root-shell-*', dan fetch no-op (tak intercept apa pun).
        [, $host] = $this->boot('swoff');

        $resp = $this->get("http://{$host}/sw.js", $this->headers($host));

        $resp->assertOk();
        $resp->assertHeader('Service-Worker-Allowed', '/');
        $this->assertStringContainsString('application/javascript', (string) $resp->headers->get('content-type'));
        $js = $resp->getContent();

        $this->assertStringContainsString('unregister', $js);
        $this->assertStringContainsString('caches.keys', $js);
        $this->assertStringContainsString('"root-shell-"', $js);
        $this->assertStringContainsString('skipWaiting', $js);
        $this->assertStringContainsString('clients.claim', $js);
        // Bukan SW normal: tak boleh ada SHELL_URLS / strategi cache shell.
        $this->assertStringNotContainsString('SHELL_URLS', $js);
    }

    public function test_service_worker_shell_only_allowlist_aman(): void
    {
        [$tenant, $host] = $this->boot('sw');
        $this->setRootSwFlag($tenant, true);

        $resp = $this->get("http://{$host}/sw.js", $this->headers($host));

        $resp->assertOk();
        $resp->assertHeader('Service-Worker-Allowed', '/');
        $js = $resp->getContent();

        // F1: shell allow-list kini 4 URL (manifest, 2 ikon, /offline).
        $this->assertStringContainsString('const SHELL_URLS = ["\/manifest.webmanifest","\/icon.svg?size=192","\/icon.svg?size=512","\/offline"]', $js);
        // Penjaga keamanan dasar tetap ada: non-GET dilewati, lintas-origin dilewati.
        $this->assertStringContainsString("method !== 'GET'", $js);
        $this->assertStringContainsString('self.location.origin', $js);
    }

    public function test_service_worker_hybrid_f1_deny_list_dan_lapis_cache(): void
    {
        [$tenant, $host] = $this->boot('hyb');
        $this->setRootSwFlag($tenant, true);

        $js = $this->get("http://{$host}/sw.js", $this->headers($host))->getContent();

        // Deny-list eksplisit: scope portal ber-SW-sendiri + jalur backend TAK
        // PERNAH ditangani SW root (defense-in-depth).
        foreach (['\/admin', '\/platform', '\/livewire', '\/api', '\/filament', '\/guru', '\/wali', '\/spmb', '\/keuangan'] as $prefix) {
            $this->assertStringContainsString($prefix, $js);
        }
        $this->assertStringContainsString('DENY_PREFIXES', $js);
        $this->assertStringContainsString('isDenied', $js);
        // Lapis 2: aset build ber-hash cache-first.
        $this->assertStringContainsString('BUILD_ASSET_RE', $js);
        $this->assertStringContainsString('/^\/build\//', $js);
        // Lapis 3: navigasi HTML network-first, fallback /offline — TIDAK cache
        // HTML ber-auth apa pun (pelajaran insiden SW /wali di F0).
        $this->assertStringContainsString('OFFLINE_URL', $js);
        $this->assertStringContainsString("mode === 'navigate'", $js);
    }

    public function test_cache_version_berubah_bila_build_manifest_vite_berubah(): void
    {
        [$tenant, $host] = $this->boot('ver');
        $this->setRootSwFlag($tenant, true);

        $buildManifest = public_path('build/manifest.json');
        $original = File::exists($buildManifest) ? File::get($buildManifest) : null;

        try {
            File::ensureDirectoryExists(public_path('build'));
            File::put($buildManifest, '{"version":"satu"}');
            $js1 = $this->get("http://{$host}/sw.js", $this->headers($host))->getContent();
            preg_match('/CACHE_NAME = "([^"]+)"/', $js1, $m1);

            File::put($buildManifest, '{"version":"dua-beda-total"}');
            $js2 = $this->get("http://{$host}/sw.js", $this->headers($host))->getContent();
            preg_match('/CACHE_NAME = "([^"]+)"/', $js2, $m2);

            $this->assertNotSame('', $m1[1] ?? '');
            $this->assertNotSame('', $m2[1] ?? '');
            $this->assertNotSame($m1[1], $m2[1], 'Cache name harus berubah saat manifest build Vite berubah.');
        } finally {
            if ($original !== null) {
                File::put($buildManifest, $original);
            } elseif (File::exists($buildManifest)) {
                File::delete($buildManifest);
            }
        }
    }

    public function test_halaman_offline_render_dengan_branding_tenant(): void
    {
        [$tenant, $host] = $this->boot('off');

        tenancy()->initialize($tenant);
        Setting::updateOrCreate(['tenant_id' => $tenant->id, 'key' => 'branding_name'], ['value' => 'Pondok Offline Uji']);
        tenancy()->end();

        $resp = $this->get("http://{$host}/offline", $this->headers($host));

        $resp->assertOk();
        $this->assertStringContainsString('Pondok Offline Uji', $resp->getContent());
        $this->assertStringContainsString('Coba Lagi', $resp->getContent());
    }

    public function test_manifest_pakai_branding_tenant(): void
    {
        [$tenant, $host] = $this->boot('br');

        tenancy()->initialize($tenant);
        Setting::updateOrCreate(['tenant_id' => $tenant->id, 'key' => 'branding_name'], ['value' => 'Pondok Uji Coba']);
        tenancy()->end();

        $this->get("http://{$host}/manifest.webmanifest", $this->headers($host))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Pondok Uji Coba']);
    }

    public function test_warna_hex_invalid_tidak_merusak(): void
    {
        [$tenant, $host] = $this->boot('hex');

        tenancy()->initialize($tenant);
        Setting::updateOrCreate(['tenant_id' => $tenant->id, 'key' => 'branding_primary_color'], ['value' => 'javascript:alert(1)']);
        tenancy()->end();

        // Warna tak valid → fallback aman, theme_color tetap hex; ikon tetap render.
        $this->get("http://{$host}/manifest.webmanifest", $this->headers($host))
            ->assertOk()
            ->assertJsonFragment(['theme_color' => '#0f5132']);

        $icon = $this->get("http://{$host}/icon.svg?size=192", $this->headers($host));
        $icon->assertOk();
        $this->assertStringNotContainsString('javascript:alert', $icon->getContent());
    }

    public function test_halaman_spmb_tidak_memuat_manifest_root_dobel(): void
    {
        // TWA /spmb live bergantung pada satu manifest tunggal (/spmb/manifest.json,
        // disuntik client-side oleh SpmbLayout.vue via Inertia <Head>). Manifest
        // root statis (app.blade.php) HARUS dikecualikan di path /spmb — kalau
        // tidak, DOM akhir (setelah Vue mount) bisa memuat DUA tag manifest.
        [, $host] = $this->boot('spmbmf');

        $resp = $this->get("http://{$host}/spmb/login", $this->headers($host));

        $resp->assertOk();
        $this->assertStringNotContainsString('/manifest.webmanifest', $resp->getContent());
    }

    public function test_halaman_root_tenant_tetap_memuat_manifest_root(): void
    {
        // Kontrol: path DI LUAR portal ber-manifest-sendiri tetap dapat manifest
        // root statis (perilaku F1 tidak menghilangkan manifest root di mana pun).
        [, $host] = $this->boot('rootmf');

        $resp = $this->get("http://{$host}/", $this->headers($host));

        $resp->assertOk();
        $this->assertStringContainsString('/manifest.webmanifest', $resp->getContent());
    }
}
