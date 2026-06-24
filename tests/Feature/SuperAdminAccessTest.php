<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Super admin harus melihat & boleh SEMUANYA: Gate::before lolos semua
 * permission (termasuk yang belum ada di tabel), bypass gerbang modul,
 * dan default Mode Lengkap. User biasa tetap dievaluasi normal.
 */
class SuperAdminAccessTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'role_has_permissions']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function bootUsers(string $prefix): array
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => true, 'data' => json_encode([]),
             'created_at' => now(), 'updated_at' => now()],
        );
        $this->seed(RolesAndPermissionsSeeder::class);

        $super = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Super', 'email' => $prefix.'-super@t.local', 'password' => 'password',
        ]);
        $super->assignRole('super_admin');

        $biasa = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Biasa', 'email' => $prefix.'-biasa@t.local', 'password' => 'password',
        ]);
        $biasa->assignRole('wali');

        return [$super, $biasa];
    }

    public function test_super_admin_lolos_semua_permission_termasuk_yang_belum_ada(): void
    {
        [$super, $biasa] = $this->bootUsers('sa1');

        // Permission yang ADA.
        $this->assertTrue($super->can('manage_keuangan'));
        $this->assertTrue($super->can('manage_ledger'));
        // Permission MASA DEPAN yang belum di-seed pun lolos (Gate::before).
        $this->assertTrue($super->can('permission_baru_yang_belum_dibuat'));

        // User biasa TIDAK ikut lolos.
        $this->assertFalse($biasa->can('manage_keuangan'));
        $this->assertFalse($biasa->can('permission_baru_yang_belum_dibuat'));
        tenancy()->end();
    }

    public function test_super_admin_bypass_gerbang_modul_dan_default_mode_lengkap(): void
    {
        [$super, $biasa] = $this->bootUsers('sa2');

        // Gerbang modul: modul 'development' (file_storage) normalnya
        // tersembunyi utk tenant non-dev — super_admin tetap melihat.
        // (Catatan: 'ai' kini 'released' sejak 2026-06-25, jadi contoh modul
        // development memakai file_storage.)
        $gate = new class
        {
            use \App\Filament\Concerns\ModuleGate;

            public static function uji(string $key): bool
            {
                return static::moduleVisible($key);
            }
        };

        Auth::login($super);
        $this->assertTrue($gate::uji('file_storage'), 'super_admin melihat modul development.');
        $this->assertSame('advanced', \App\Support\UiMode::current(), 'super_admin default Mode Lengkap.');

        Auth::login($biasa);
        $this->assertSame('simple', \App\Support\UiMode::current(), 'user biasa default Mode Sederhana.');
        tenancy()->end();
    }

    /**
     * Fitur ② — grup nav mulai ter-ciut per peran (primer terbuka, sekunder
     * terciut); peran luas (super_admin) → semua terbuka. RBAC tak terdampak.
     */
    public function test_grup_nav_terciut_sesuai_peran(): void
    {
        [$super] = $this->bootUsers('navrole');

        $guru = User::withoutGlobalScopes()->create([
            'tenant_id' => $super->tenant_id, 'name' => 'Guru', 'email' => 'navrole-guru@t.local', 'password' => 'password',
        ]);
        $guru->assignRole('guru');

        $bendahara = User::withoutGlobalScopes()->create([
            'tenant_id' => $super->tenant_id, 'name' => 'Bendahara', 'email' => 'navrole-bdh@t.local', 'password' => 'password',
        ]);
        $bendahara->assignRole('bendahara');

        // Peran blok kesantrian atomik (Fase 1).
        $konselor = User::withoutGlobalScopes()->create([
            'tenant_id' => $super->tenant_id, 'name' => 'Konselor', 'email' => 'navrole-konselor@t.local', 'password' => 'password',
        ]);
        $konselor->assignRole('konselor');

        $uks = User::withoutGlobalScopes()->create([
            'tenant_id' => $super->tenant_id, 'name' => 'UKS', 'email' => 'navrole-uks@t.local', 'password' => 'password',
        ]);
        $uks->assignRole('pengelola_uks');

        $ciut = fn (string $g): bool => \App\Providers\Filament\TenantPanelProvider::grupTerciutUntukPeran($g);

        // Guru: Akademik & Santri primer (terbuka), Keuangan/Mutu sekunder (terciut).
        Auth::login($guru);
        $this->assertFalse($ciut('Akademik'), 'guru: Akademik primer harus terbuka');
        $this->assertFalse($ciut('Santri'), 'guru: Santri primer harus terbuka');
        $this->assertTrue($ciut('Akuntansi'), 'guru: Akuntansi sekunder harus terciut');
        $this->assertTrue($ciut('Mutu'), 'guru: Mutu sekunder harus terciut');

        // Bendahara: Tagihan & Pembayaran primer (terbuka), Akademik sekunder (terciut).
        Auth::login($bendahara);
        $this->assertFalse($ciut('Tagihan & Pembayaran'), 'bendahara: Tagihan & Pembayaran primer harus terbuka');
        $this->assertTrue($ciut('Akademik'), 'bendahara: Akademik sekunder harus terciut');

        // Konselor: Konseling primer (terbuka), grup lain sekunder (terciut).
        Auth::login($konselor);
        $this->assertFalse($ciut('Konseling'), 'konselor: Konseling primer harus terbuka');
        $this->assertTrue($ciut('Akuntansi'), 'konselor: Akuntansi sekunder harus terciut');

        // Pengelola UKS: Kesehatan primer (terbuka), grup lain sekunder (terciut).
        Auth::login($uks);
        $this->assertFalse($ciut('Kesehatan'), 'uks: Kesehatan primer harus terbuka');
        $this->assertTrue($ciut('Akademik'), 'uks: Akademik sekunder harus terciut');

        // Super_admin (peran luas) → semua grup terbuka.
        Auth::login($super);
        $this->assertFalse($ciut('Akuntansi'), 'super_admin: semua grup terbuka');
        $this->assertFalse($ciut('Mutu'), 'super_admin: semua grup terbuka');

        tenancy()->end();
    }
}
