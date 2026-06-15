<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\WaliImportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WaliImportService: generate akun Portal Wali dari santri aktif beremail.
 * Menguji dry-run (tak menulis), live (buat+tautkan+role+CSV), idempoten,
 * dan santri tanpa email dilewati. Logika diekstrak dari ImportWaliCommand.
 */
class WaliImportServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'model_has_roles', 'model_has_permissions', 'santri',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('wali', 'Pesantren Wali');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function santri(string $nis, string $nama, ?string $emailWali, string $status = 'aktif'): Santri
    {
        return Santri::create([
            'tenant_id' => $this->tenant->id,
            'nis' => $nis,
            'nama' => $nama,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status' => $status,
            'nama_ayah' => 'Ayah '.$nama,
            'no_hp_wali' => $emailWali,
        ]);
    }

    public function test_dry_run_tidak_membuat_akun(): void
    {
        $this->santri('001', 'Ahmad', '081234567001');

        $sebelum = User::query()->count();
        $hasil = app(WaliImportService::class)->generate((string) $this->tenant->id, true);

        $this->assertSame(1, $hasil['total']);
        $this->assertSame(1, $hasil['created']);
        $this->assertSame($sebelum, User::query()->count(), 'dry-run tidak boleh menulis user');
        $this->assertNull($hasil['csv_path']);
    }

    public function test_live_membuat_akun_menautkan_dan_idempoten(): void
    {
        $santri = $this->santri('002', 'Budi', '081234567002');
        $this->santri('003', 'Caca', null); // tanpa no HP → dilewati

        $hasil = app(WaliImportService::class)->generate((string) $this->tenant->id, false);

        $this->assertSame(1, $hasil['created']);
        $this->assertSame(1, $hasil['total'], 'santri tanpa no HP tidak masuk query');

        $phone = \App\Support\PhoneNumber::normalize('081234567002');
        $user = User::query()->where('phone', $phone)->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('wali'));
        $this->assertSame($user->id, $santri->fresh()->wali_user_id);
        $this->assertNotNull($hasil['csv_path']);

        // Jalankan ulang → tidak membuat user dobel (idempoten).
        $ulang = app(WaliImportService::class)->generate((string) $this->tenant->id, false);
        $this->assertSame(0, $ulang['created']);
        $this->assertSame(1, $ulang['existing']);
        $this->assertSame(1, User::query()->where('phone', $phone)->count());

        @unlink((string) $hasil['csv_path']);
    }
}
