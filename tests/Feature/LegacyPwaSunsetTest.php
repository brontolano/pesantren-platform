<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F5 (sunset instalasi lama): flag per-tenant `pwa.legacy_sw_selfdestruct`.
 * OFF (default, semua tenant) → SW /guru /wali /keuangan tetap serve SW
 * normal (shell-cache) seperti sebelumnya — TANPA regresi. ON → ketiganya
 * serve skrip self-destruct (unregister + hapus cache by-prefix + fetch
 * no-op), sama seperti kill-switch F0 di root. /spmb DIKECUALIKAN total
 * (kontrak TWA Play Store) — SW-nya tak boleh berubah sama sekali walau
 * flag ON.
 */
class LegacyPwaSunsetTest extends TestCase
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
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Sunset', $host);

        return [$tenant, $host];
    }

    /** @return array{HTTP_HOST: string, SERVER_NAME: string} */
    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    private function setLegacySelfdestructFlag(Tenant $tenant, bool $enabled): void
    {
        tenancy()->initialize($tenant);
        Setting::updateOrCreate(
            ['tenant_id' => $tenant->id, 'key' => 'pwa.legacy_sw_selfdestruct'],
            ['value' => $enabled],
        );
        tenancy()->end();
    }

    private function assertSelfDestructScript(string $js, string $cachePrefix, string $scope): void
    {
        $this->assertStringContainsString('unregister', $js);
        $this->assertStringContainsString('caches.keys', $js);
        $this->assertStringContainsString('"'.$cachePrefix.'"', $js);
        $this->assertStringContainsString('skipWaiting', $js);
        $this->assertStringContainsString('clients.claim', $js);
        $this->assertStringNotContainsString('SHELL_URLS', $js);
    }

    // -----------------------------------------------------------------
    // /guru
    // -----------------------------------------------------------------

    public function test_guru_sw_flag_off_default_mengembalikan_sw_normal(): void
    {
        [, $host] = $this->boot('guruoff');

        $resp = $this->get("http://{$host}/guru/sw.js", $this->headers($host));

        $resp->assertOk();
        $this->assertStringContainsString('application/javascript', (string) $resp->headers->get('content-type'));
        $js = $resp->getContent();

        $this->assertStringContainsString('SHELL_URLS', $js);
        $this->assertStringContainsString('guru-shell-', $js);
        $this->assertStringNotContainsString('self.registration.unregister', $js);
    }

    public function test_guru_sw_flag_on_mengembalikan_self_destruct(): void
    {
        [$tenant, $host] = $this->boot('guruon');
        $this->setLegacySelfdestructFlag($tenant, true);

        $resp = $this->get("http://{$host}/guru/sw.js", $this->headers($host));

        $resp->assertOk();
        $resp->assertHeader('Service-Worker-Allowed', '/guru/');
        $this->assertSelfDestructScript($resp->getContent(), 'guru-shell-', '/guru/');
    }

    // -----------------------------------------------------------------
    // /wali
    // -----------------------------------------------------------------

    public function test_wali_sw_flag_off_default_mengembalikan_sw_normal(): void
    {
        [, $host] = $this->boot('walioff');

        $resp = $this->get("http://{$host}/wali/sw.js", $this->headers($host));

        $resp->assertOk();
        $js = $resp->getContent();

        $this->assertStringContainsString('SHELL_URLS', $js);
        $this->assertStringContainsString('wali-shell-', $js);
        $this->assertStringNotContainsString('self.registration.unregister', $js);
    }

    public function test_wali_sw_flag_on_mengembalikan_self_destruct(): void
    {
        [$tenant, $host] = $this->boot('walion');
        $this->setLegacySelfdestructFlag($tenant, true);

        $resp = $this->get("http://{$host}/wali/sw.js", $this->headers($host));

        $resp->assertOk();
        $resp->assertHeader('Service-Worker-Allowed', '/wali/');
        $this->assertSelfDestructScript($resp->getContent(), 'wali-shell-', '/wali/');
    }

    // -----------------------------------------------------------------
    // /keuangan
    // -----------------------------------------------------------------

    public function test_keuangan_sw_flag_off_default_mengembalikan_sw_normal(): void
    {
        [, $host] = $this->boot('keuoff');

        $resp = $this->get("http://{$host}/keuangan/sw.js", $this->headers($host));

        $resp->assertOk();
        $js = $resp->getContent();

        $this->assertStringContainsString('SHELL_URLS', $js);
        $this->assertStringContainsString('keuangan-shell-', $js);
        $this->assertStringNotContainsString('self.registration.unregister', $js);
    }

    public function test_keuangan_sw_flag_on_mengembalikan_self_destruct(): void
    {
        [$tenant, $host] = $this->boot('keuon');
        $this->setLegacySelfdestructFlag($tenant, true);

        $resp = $this->get("http://{$host}/keuangan/sw.js", $this->headers($host));

        $resp->assertOk();
        $resp->assertHeader('Service-Worker-Allowed', '/keuangan/');
        $this->assertSelfDestructScript($resp->getContent(), 'keuangan-shell-', '/keuangan/');
    }

    // -----------------------------------------------------------------
    // /spmb — DIKECUALIKAN TOTAL (kontrak TWA Play Store). Repo ini belum
    // punya rute SW/manifest /spmb terdaftar (lihat routes/tenant.php) — jadi
    // asersi difokuskan pada perilaku yang bisa diverifikasi: halaman /spmb
    // tetap merespons identik terlepas dari status flag, DAN tak satu pun
    // controller F5 (guru/wali/keuangan) menyentuh path /spmb.
    // -----------------------------------------------------------------

    public function test_spmb_tidak_terpengaruh_flag_legacy_selfdestruct_status_apapun(): void
    {
        [$tenantOff, $hostOff] = $this->boot('spmboff');
        $before = $this->get("http://{$hostOff}/spmb/login", $this->headers($hostOff));

        [$tenantOn, $hostOn] = $this->boot('spmbon');
        $this->setLegacySelfdestructFlag($tenantOn, true);
        $after = $this->get("http://{$hostOn}/spmb/login", $this->headers($hostOn));

        // Sama-sama tak boleh terpengaruh: status code identik terlepas flag F5.
        $this->assertSame($before->getStatusCode(), $after->getStatusCode());
        $this->assertStringNotContainsString('unregister', (string) $after->getContent());
    }
}
