<?php

namespace Tests\Feature;

use App\Models\TenantModule;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Scoping per-tenant: status modul aktif (tabel central tenant_modules) +
 * trait BelongsToTenant (global scope tenant_id + auto-stamp saat create) sebagai
 * defense-in-depth pada query yang berbagi koneksi.
 *
 * CATATAN: isolasi data lintas-tenant yang SEBENARNYA (database fisik terpisah)
 * diuji di TenantPhysicalIsolationTest. Versi lama test ini memakai
 * RefreshDatabase + Tenant::create yang memicu auto-provisioning (CREATE DATABASE
 * di dalam transaksi → gagal) sehingga MERAH & memberi rasa-aman palsu. Ditulis
 * ulang memakai harness DB test kanonik (createRuntimeTenant TIDAK mem-provision
 * DB fisik; koneksi 'tenant' = DB test yang sama, jadi global scope teruji apa adanya).
 */
class TenantIsolationTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'tenant_modules', 'users']);
        $this->truncatePpdbTables(['tenant_modules', 'users', 'domains', 'tenants']);
    }

    public function test_status_modul_aktif_terpisah_per_tenant(): void
    {
        $idA = 'mod-a-'.Str::lower(Str::random(6));
        $idB = 'mod-b-'.Str::lower(Str::random(6));
        $this->createTestTenant($idA, 'Pesantren A', $idA.'.localhost');
        $this->createTestTenant($idB, 'Pesantren B', $idB.'.localhost');

        TenantModule::create(['tenant_id' => $idA, 'module_name' => 'Hello', 'is_active' => true]);
        TenantModule::create(['tenant_id' => $idB, 'module_name' => 'Hello', 'is_active' => false]);

        $this->assertTrue(TenantModule::isModuleActive($idA, 'Hello'), 'modul aktif di tenant A');
        $this->assertFalse(TenantModule::isModuleActive($idB, 'Hello'), 'modul nonaktif di tenant B');
        // Modul yang tak terdaftar = tidak aktif (default tertutup).
        $this->assertFalse(TenantModule::isModuleActive($idA, 'TidakAda'), 'modul tak terdaftar default nonaktif');
    }

    public function test_belongs_to_tenant_auto_stamp_tenant_id_saat_create(): void
    {
        // Defense-in-depth: model BelongsToTenant menstempel tenant_id dari konteks
        // tenancy saat dibuat tanpa tenant_id eksplisit (App\Traits\BelongsToTenant).
        $tenant = $this->createRuntimeTenant('stamp', 'Pesantren Stamp');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        $user = User::create([
            'name' => 'Tanpa Tenant Eksplisit',
            'email' => 'stamp-'.Str::lower(Str::random(6)).'@t.local',
            'password' => bcrypt('rahasia-uji'),
        ]);

        $this->assertSame($tenant->id, $user->tenant_id, 'tenant_id WAJIB ter-stempel otomatis dari konteks.');
        tenancy()->end();
    }

    public function test_global_scope_menyaring_query_per_tenant(): void
    {
        // Defense-in-depth: TenantScope menyaring query model ke tenant aktif saja,
        // walau baris milik tenant lain ada di tabel yang sama (koneksi berbagi).
        $tenantA = $this->createRuntimeTenant('scopa', 'Pesantren A');
        $tenantB = $this->createRuntimeTenant('scopb', 'Pesantren B');

        tenancy()->initialize($tenantA);
        $this->seedTenantRow($tenantA);
        User::create(['name' => 'A', 'email' => 'a-'.Str::lower(Str::random(6)).'@t.local', 'password' => bcrypt('x')]);
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $this->seedTenantRow($tenantB);
        User::create(['name' => 'B', 'email' => 'b-'.Str::lower(Str::random(6)).'@t.local', 'password' => bcrypt('x')]);

        // Dalam konteks B, global scope hanya kembalikan data tenant B (bukan A).
        $this->assertCount(1, User::all(), 'global scope WAJIB hanya kembalikan data tenant aktif.');
        $this->assertSame($tenantB->id, User::first()->tenant_id, 'data yang terlihat milik tenant aktif (B), bukan A.');
        tenancy()->end();
    }
}
