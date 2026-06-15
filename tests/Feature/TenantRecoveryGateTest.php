<?php

namespace Tests\Feature;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Gerbang fitur backup/restore/RESET: HANYA super_admin pemilik platform
 * (tenant_id null). Staf tenant berperan super_admin lokal HARUS ditolak.
 * Plus jalur batal CLI tenant:reset (tanpa menyentuh shell/DB tenant).
 */
class TenantRecoveryGateTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'roles']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    public function test_can_recovery_hanya_super_admin_platform(): void
    {
        // null → tolak
        $this->assertFalse(TenantResource::canRecovery(null));

        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // super_admin pemilik platform (tenant_id null) → izinkan
        $platform = User::create([
            'tenant_id' => null, 'name' => 'Owner', 'email' => 'owner@platform.test', 'password' => 'rahasia123',
        ]);
        $platform->assignRole($role);
        $this->assertTrue(TenantResource::canRecovery($platform->fresh()));

        // staf tenant berperan super_admin lokal → TETAP DITOLAK (tenant_id != null)
        $this->createTestTenant('gate-x', 'Gate X', 'gate-x.localhost');
        $stafTenant = User::create([
            'tenant_id' => 'gate-x', 'name' => 'Admin Tenant', 'email' => 'admin@gate-x.test', 'password' => 'rahasia123',
        ]);
        $stafTenant->assignRole($role);
        $this->assertFalse(TenantResource::canRecovery($stafTenant->fresh()), 'staf tenant tak boleh akses recovery platform');
    }

    public function test_cli_reset_tenant_tak_ada_gagal(): void
    {
        $this->artisan('tenant:reset', ['tenant' => 'tidak-ada', '--force' => true])
            ->expectsOutputToContain('tidak ditemukan')
            ->assertExitCode(1);
    }

    public function test_cli_reset_konfirmasi_salah_dibatalkan(): void
    {
        // Baris tenant via insert mentah (TANPA memicu pembuatan DB fisik) — cukup
        // utk menguji jalur batal; reset() tak pernah terpanggil saat konfirmasi salah.
        $this->createTestTenant('gate-y', 'Gate Y', 'gate-y.localhost');

        $this->artisan('tenant:reset', ['tenant' => 'gate-y'])
            ->expectsQuestion("Ketik persis ID tenant ('gate-y') untuk melanjutkan", 'salah-ketik')
            ->expectsOutputToContain('dibatalkan')
            ->assertExitCode(1);
    }
}
