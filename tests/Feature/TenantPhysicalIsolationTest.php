<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Choke point #1 (rencana red-team 2026-06-27): ISOLASI DB-FISIK lintas-tenant.
 *
 * Berbeda dari TenantIsolationTest yang menguji global-scope tenant_id pada
 * satu DB (defense-in-depth), test ini menguji MEKANISME PRODUKSI sebenarnya:
 * database FISIK terpisah per tenant (keputusan 2026-06-05). Memakai tenant
 * yang BENAR-BENAR di-provision (TenantProvisioner) + $tenant->run() sehingga
 * koneksi 'tenant' berpindah ke DB tenant masing-masing.
 *
 * Tulis penanda di DB tenant A → harus (a) ada di A, (b) TIDAK ada di B,
 * (c) TIDAK bocor ke DB central. Kalau ada model salah-koneksi, test MERAH.
 */
class TenantPhysicalIsolationTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'tenant_storage', 'users', 'roles', 'permissions',
            'model_has_roles', 'settings',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    public function test_data_tenant_tidak_bocor_ke_db_tenant_lain_atau_central(): void
    {
        $idA = 'isoa'.Str::lower(Str::random(6));
        $idB = 'isob'.Str::lower(Str::random(6));
        $tenantA = $this->createProvisionedTenant($idA, 'Pesantren A', $idA.'.localhost');
        $tenantB = $this->createProvisionedTenant($idB, 'Pesantren B', $idB.'.localhost');

        $marker = 'penanda-'.Str::lower(Str::random(10)).'@isolasi.test';

        // Tulis penanda HANYA di DB tenant A.
        $tenantA->run(function () use ($marker) {
            User::forceCreate([
                'name' => 'Penanda Isolasi',
                'email' => $marker,
                'password' => bcrypt('rahasia-uji'),
            ]);
        });

        // (a) sanity: ada di DB tenant A.
        $tenantA->run(function () use ($marker) {
            $this->assertTrue(
                User::query()->where('email', $marker)->exists(),
                'Penanda harus ada di DB tenant pembuatnya (A).',
            );
        });

        // (b) ISOLASI: TIDAK ada di DB tenant B.
        $tenantB->run(function () use ($marker) {
            $this->assertFalse(
                User::query()->where('email', $marker)->exists(),
                'KEBOCORAN LINTAS-TENANT: data tenant A terlihat di DB tenant B.',
            );
        });

        // (c) TIDAK bocor ke DB central.
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }
        $this->assertFalse(
            DB::connection('pgsql')->table('users')->where('email', $marker)->exists(),
            'KEBOCORAN: data tenant tertulis ke DB central.',
        );
    }
}
