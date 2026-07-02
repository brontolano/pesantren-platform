<?php

namespace Tests\Feature;

use App\Models\TenantModule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Ledger\Filament\Resources\PeriodeAkuntansiResource;
use Modules\Payroll\Filament\Resources\SlipGajiResource;
use Modules\Pengadaan\Filament\Resources\BelanjaResource;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Invarian I-5 (#28) — RBAC & segregation of duties keuangan yang BELUM teruji.
 *
 * Melengkapi LedgerAksesTest (bendahara vs guru) & PermissionAccessTest
 * (manage_users) dengan gate aksi uang paling sensitif:
 *  - close_period   : kunci periode → BUKAN untuk bendahara biasa.
 *  - approve_pencairan : pemisahan input vs persetujuan belanja.
 *  - run_payroll    : penggajian terbatas role berwenang.
 *  - manage_ledger  : operator boleh lihat tapi tak boleh buat periode.
 *
 * Tidak menyentuh ModuleRegistrySeeder → gating modul netral (modul tak
 * terdaftar = visible) sehingga yang diuji murni lapis PERMISSION.
 */
class RbacSegregationTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('kebijakan_kehalalan')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables(['domains', 'tenants', 'users']);

        $tenant = $this->createRuntimeTenant('rbac', 'Pesantren RBAC');

        // Pastikan modul keuangan yang diuji TAMPAK untuk tenant ini agar yang
        // diuji murni lapis PERMISSION (bukan gating modul) — deterministik
        // terlepas dari state ModuleRegistry yang mungkin di-seed test lain.
        // DILAKUKAN DI KONTEKS CENTRAL (sebelum init tenancy) karena tenant_modules
        // adalah tabel central.
        foreach (['ledger', 'payroll', 'pengadaan'] as $modul) {
            TenantModule::firstOrCreate(
                ['tenant_id' => $tenant->getKey(), 'module_name' => $modul],
                ['is_active' => true],
            );
        }

        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        (new RolesAndPermissionsSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function userDenganRole(string $role): User
    {
        $user = User::withoutGlobalScopes()->create([
            'name' => ucfirst($role),
            'email' => $role.'@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    public function test_close_period_hanya_untuk_peran_pusat_bukan_bendahara_biasa(): void
    {
        // Bendahara biasa TIDAK boleh mengunci/membuka periode.
        $this->assertFalse(
            $this->userDenganRole('bendahara')->can('close_period'),
            'Bendahara biasa tidak boleh menutup/membuka periode (integritas kunci periode).'
        );

        // Peran pusat/kepala BOLEH.
        $this->assertTrue($this->userDenganRole('bendahara_pusat')->can('close_period'));
        $this->assertTrue($this->userDenganRole('kepala_keuangan')->can('close_period'));
    }

    public function test_approve_pencairan_terpisah_dari_input_belanja(): void
    {
        $operator = $this->userDenganRole('operator_divisi');
        $this->actingAs($operator);

        // Operator boleh MENGINPUT belanja…
        $this->assertTrue(BelanjaResource::canCreate(), 'Operator divisi boleh input belanja.');
        // …tetapi TIDAK boleh MENYETUJUI pencairan (segregation of duties).
        $this->assertFalse($operator->can('approve_pencairan'), 'Operator divisi tidak boleh approve pencairan.');

        // Bendahara pusat boleh menyetujui.
        $this->assertTrue($this->userDenganRole('bendahara_pusat')->can('approve_pencairan'));
    }

    public function test_payroll_hanya_role_berwenang(): void
    {
        $this->actingAs($this->userDenganRole('kasir'));
        $this->assertFalse(SlipGajiResource::canAccess(), 'Kasir tidak boleh akses Payroll.');

        $this->actingAs($this->userDenganRole('bendahara'));
        $this->assertFalse(SlipGajiResource::canAccess(), 'Bendahara biasa (tanpa run_payroll) tidak boleh akses Payroll.');

        $this->actingAs($this->userDenganRole('bendahara_pusat'));
        $this->assertTrue(SlipGajiResource::canAccess(), 'Bendahara pusat (run_payroll) boleh akses Payroll.');
    }

    public function test_operator_boleh_lihat_tapi_tak_boleh_buat_periode_ledger(): void
    {
        $this->actingAs($this->userDenganRole('operator_divisi'));
        $this->assertTrue(PeriodeAkuntansiResource::canAccess(), 'Operator (view_ledger) boleh lihat periode.');
        $this->assertFalse(PeriodeAkuntansiResource::canCreate(), 'Operator (tanpa manage_ledger) tak boleh buat periode.');

        $this->actingAs($this->userDenganRole('bendahara'));
        $this->assertTrue(PeriodeAkuntansiResource::canCreate(), 'Bendahara (manage_ledger) boleh buat periode.');
    }

    public function test_peran_non_keuangan_tak_bisa_akses_payroll_maupun_belanja(): void
    {
        $this->actingAs($this->userDenganRole('guru'));

        $this->assertFalse(SlipGajiResource::canAccess(), 'Guru tidak boleh akses Payroll.');
        $this->assertFalse(BelanjaResource::canAccess(), 'Guru tidak boleh akses Belanja/Pengadaan.');
        $this->assertFalse(PeriodeAkuntansiResource::canAccess(), 'Guru tidak boleh akses Periode Ledger.');
    }
}
