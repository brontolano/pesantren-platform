<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Auth aplikasi NATIVE (token Sanctum): /api/v1/auth/{login,me,logout,demo}.
 * Per-tenant (resolve domain). Demo digerbang DEMO_AUTOLOGIN + tenant demo.
 */
class AuthApiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'tenant_storage', 'users', 'roles', 'permissions',
            'model_has_roles', 'settings', 'personal_access_tokens',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    /** @return array{0: Tenant, 1: string, 2: string} */
    private function bootTenant(bool $demo = false): array
    {
        $host = 'auth-'.Str::lower(Str::random(6)).'.localhost';
        $tenantId = explode('.', $host)[0];
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Uji', $host);

        // Peran jemaah + izin komentar belum di-provision otomatis → buat di sini.
        $tenant->run(function () {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $perm = Permission::firstOrCreate(['name' => 'komentar']);
            Role::firstOrCreate(['name' => 'jemaah'])->givePermissionTo($perm);
        });

        if ($demo) {
            config(['demo.autologin_enabled' => true, 'demo.autologin_tenant' => $tenantId]);
        }

        return [$tenant, $host, $tenantId];
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    private function buatJemaah(Tenant $tenant): void
    {
        $tenant->run(function () {
            // password cast 'hashed' → kirim plaintext (auto-hash sekali).
            User::create([
                'name' => 'Warga Demo',
                'email' => 'warga@uji.test',
                'phone' => '6281200000001',
                'password' => 'rahasia123',
            ])->assignRole('jemaah');
        });
    }

    public function test_demo_login_keluarkan_token_lalu_me_dan_logout(): void
    {
        [$tenant, $host] = $this->bootTenant(demo: true);

        $resp = $this->postJson("http://{$host}/api/v1/auth/demo/jemaah", [], $this->headers($host));
        $resp->assertOk()->assertJsonPath('ok', true);
        $token = $resp->json('data.token');
        $this->assertNotEmpty($token);
        $this->assertContains('jemaah', $resp->json('data.user.roles'));

        $auth = array_merge($this->headers($host), ['Authorization' => "Bearer {$token}"]);
        $this->getJson("http://{$host}/api/v1/auth/me", $auth)
            ->assertOk()
            ->assertJsonPath('data.name', 'Jemaah Demo');

        $this->postJson("http://{$host}/api/v1/auth/logout", [], $auth)->assertOk();
        // Logout MENCABUT token: barisnya benar-benar terhapus dari DB tenant.
        // (Verifikasi via DB; di harness feature-test state auth ter-cache antar
        // request dalam satu test, jadi cek 401 tak andal — cek baris DB yg andal.)
        $sisa = $tenant->run(fn () => PersonalAccessToken::query()->count());
        $this->assertSame(0, $sisa);
    }

    public function test_login_jemaah_kredensial_benar_keluarkan_token(): void
    {
        [$tenant, $host] = $this->bootTenant();
        $this->buatJemaah($tenant);

        $resp = $this->postJson("http://{$host}/api/v1/auth/login", [
            'identifier' => 'warga@uji.test',
            'password' => 'rahasia123',
        ], $this->headers($host));

        $resp->assertOk()->assertJsonPath('ok', true);
        $this->assertNotEmpty($resp->json('data.token'));
        $this->assertSame(true, $resp->json('data.user.canComment'));
    }

    public function test_login_password_salah_ditolak_seragam(): void
    {
        [$tenant, $host] = $this->bootTenant();
        $this->buatJemaah($tenant);

        $this->postJson("http://{$host}/api/v1/auth/login", [
            'identifier' => 'warga@uji.test',
            'password' => 'salah-banget',
        ], $this->headers($host))->assertStatus(422);
    }

    public function test_demo_login_404_tanpa_flag(): void
    {
        [, $host] = $this->bootTenant(demo: false);

        $this->postJson("http://{$host}/api/v1/auth/demo/jemaah", [], $this->headers($host))
            ->assertNotFound();
    }
}
