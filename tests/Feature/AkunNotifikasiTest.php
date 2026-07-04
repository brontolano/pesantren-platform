<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Notifikasi\Models\LogNotifikasi;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F4 — /akun (profil + ubah password) & /notifikasi (riwayat notifikasi)
 * lintas-peran: HANYA middleware `auth`, tersedia untuk siapa pun user
 * tenant yang login, tanpa permission khusus.
 *
 * Pola DB-per-tenant runtime (bukan RefreshDatabase — CREATE DATABASE tidak
 * bisa jalan di dalam transaksi Postgres), mengikuti TenantPanelAuthedSmokeTest
 * & PesertaPortalDashboardTest (routing via Host tenant dengan ->call()).
 */
class AkunNotifikasiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'log_notifikasi',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('f4akun', 'Pesantren F4 Akun');
        $this->host = "{$this->tenant->id}.localhost";
        tenancy()->initialize($this->tenant);
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $this->tenant->id],
            ['name' => $this->tenant->name, 'is_active' => true, 'data' => json_encode([]),
                'created_at' => now(), 'updated_at' => now()],
        );
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function makeUser(string $name, string $email, string $password = 'password-lama'): User
    {
        return User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
    }

    public function test_get_akun_sebagai_user_terautentikasi(): void
    {
        $user = $this->makeUser('Guru A', 'guru-a@pesantren.test');

        $response = $this
            ->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->call('GET', "http://{$this->host}/akun", [], [], [], [
                'HTTP_HOST' => $this->host,
                'SERVER_NAME' => $this->host,
            ]);

        $response->assertOk();
        $response->assertSee('Akun\\/Profil', false);
        $response->assertSee('guru-a@pesantren.test');
    }

    public function test_update_password_gagal_jika_current_password_salah(): void
    {
        $user = $this->makeUser('Guru A', 'guru-a@pesantren.test');

        $response = $this
            ->actingAs($user)
            ->call('POST', "http://{$this->host}/akun/password", [
                'current_password' => 'password-salah',
                'password' => 'password-baru-123',
                'password_confirmation' => 'password-baru-123',
            ], [], [], [
                'HTTP_HOST' => $this->host,
                'SERVER_NAME' => $this->host,
            ]);

        $response->assertSessionHasErrors('current_password');

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('password-lama', $fresh->password));
        $this->assertFalse(Hash::check('password-baru-123', $fresh->password));
    }

    public function test_update_password_berhasil_dengan_data_valid(): void
    {
        $user = $this->makeUser('Guru A', 'guru-a@pesantren.test');

        $response = $this
            ->actingAs($user)
            ->call('POST', "http://{$this->host}/akun/password", [
                'current_password' => 'password-lama',
                'password' => 'password-baru-123',
                'password_confirmation' => 'password-baru-123',
            ], [], [], [
                'HTTP_HOST' => $this->host,
                'SERVER_NAME' => $this->host,
            ]);

        $response->assertSessionDoesntHaveErrors();

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('password-baru-123', $fresh->password));
        $this->assertFalse(Hash::check('password-lama', $fresh->password));
    }

    public function test_get_notifikasi_sebagai_user_terautentikasi(): void
    {
        $user = $this->makeUser('Guru A', 'guru-a@pesantren.test');

        $response = $this
            ->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->call('GET', "http://{$this->host}/notifikasi", [], [], [], [
                'HTTP_HOST' => $this->host,
                'SERVER_NAME' => $this->host,
            ]);

        $response->assertOk();
        $response->assertSee('Notifikasi\\/Index', false);
    }

    public function test_notifikasi_hanya_menampilkan_milik_user_sendiri(): void
    {
        $userA = $this->makeUser('Guru A', 'guru-a@pesantren.test');
        $this->makeUser('Kasir B', 'kasir-b@pesantren.test');

        LogNotifikasi::create([
            'tenant_id' => $this->tenant->id,
            'jenis' => 'info',
            'channel' => 'wa',
            'target' => 'guru-a@pesantren.test',
            'status' => 'sent',
            'payload' => ['pesan' => 'Untuk Guru A'],
            'created_at' => now(),
        ]);

        LogNotifikasi::create([
            'tenant_id' => $this->tenant->id,
            'jenis' => 'info',
            'channel' => 'wa',
            'target' => 'kasir-b@pesantren.test',
            'status' => 'sent',
            'payload' => ['pesan' => 'Untuk Kasir B'],
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($userA)
            ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->call('GET', "http://{$this->host}/notifikasi", [], [], [], [
                'HTTP_HOST' => $this->host,
                'SERVER_NAME' => $this->host,
            ]);

        $response->assertOk();
        $response->assertSee('guru-a@pesantren.test');
        $response->assertDontSee('kasir-b@pesantren.test');
    }
}
