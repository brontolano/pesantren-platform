<?php

namespace Tests\Feature;

use App\Filament\Widgets\KategoriPengeluaranChart;
use App\Filament\Widgets\KeuanganAreaChart;
use App\Filament\Widgets\KeuanganOverviewWidget;
use App\Filament\Widgets\TransaksiTerbaru;
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

    public function test_super_admin_pesantren_ikut_gerbang_modul_dan_default_mode_lengkap(): void
    {
        [$super, $biasa] = $this->bootUsers('sa2');

        // Registry modul DETERMINISTIK. `module_registry` ada di koneksi CENTRAL
        // (ModuleRegistry::getConnectionName) dan PERSISTEN lintas test (setUp
        // hanya truncate domains+tenants), jadi tanpa seed eksplisit test ini
        // bergantung pada apakah baris file_storage kebetulan ada — sumber drift.
        $this->seed(\Database\Seeders\ModuleRegistrySeeder::class);

        $gate = new class
        {
            use \App\Filament\Concerns\ModuleGate;

            public static function uji(string $key): bool
            {
                return static::moduleVisible($key);
            }
        };

        // Super admin PESANTREN (punya tenant, non-demo) IKUT gerbang modul agar
        // navigasi tetap ramping — bypass HANYA untuk pemilik PLATFORM (super_admin
        // tanpa tenant), lihat App\Filament\Concerns\ModuleGate::moduleVisible.
        // Maka modul 'development' (file_storage) TETAP tersembunyi untuknya,
        // sementara modul INTI (kesantrian) tetap tampil.
        Auth::login($super);
        $this->assertFalse($gate::uji('file_storage'), 'super_admin pesantren TIDAK melihat modul development (nav ramping).');
        $this->assertTrue($gate::uji('kesantrian'), 'modul inti selalu terlihat.');
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
        $this->assertFalse($ciut('Pembelajaran'), 'guru: Akademik primer harus terbuka');
        $this->assertFalse($ciut('Santri'), 'guru: Santri primer harus terbuka');
        $this->assertTrue($ciut('Buku Besar'), 'guru: Akuntansi sekunder harus terciut');
        $this->assertTrue($ciut('Mutu'), 'guru: Mutu sekunder harus terciut');

        // Bendahara: Tagihan & Pembayaran primer (terbuka), Akademik sekunder (terciut).
        Auth::login($bendahara);
        $this->assertFalse($ciut('Tagihan Santri'), 'bendahara: Tagihan & Pembayaran primer harus terbuka');
        $this->assertTrue($ciut('Pembelajaran'), 'bendahara: Akademik sekunder harus terciut');

        // Konselor: Konseling primer (terbuka), grup lain sekunder (terciut).
        Auth::login($konselor);
        $this->assertFalse($ciut('Konseling'), 'konselor: Konseling primer harus terbuka');
        $this->assertTrue($ciut('Buku Besar'), 'konselor: Akuntansi sekunder harus terciut');

        // Pengelola UKS: Kesehatan primer (terbuka), grup lain sekunder (terciut).
        Auth::login($uks);
        $this->assertFalse($ciut('Kesehatan'), 'uks: Kesehatan primer harus terbuka');
        $this->assertTrue($ciut('Pembelajaran'), 'uks: Akademik sekunder harus terciut');

        // Super_admin (peran luas) → semua grup terbuka.
        Auth::login($super);
        $this->assertFalse($ciut('Buku Besar'), 'super_admin: semua grup terbuka');
        $this->assertFalse($ciut('Mutu'), 'super_admin: semua grup terbuka');

        tenancy()->end();
    }

    /**
     * Fase 1 — widget keuangan WAJIB tergerbang per peran: guru (tanpa
     * view_keuangan) TIDAK melihat angka kas/tagihan; bendahara melihatnya.
     */
    public function test_widget_keuangan_tergerbang_per_peran(): void
    {
        [$super] = $this->bootUsers('widgetuang');

        $guru = User::withoutGlobalScopes()->create([
            'tenant_id' => $super->tenant_id, 'name' => 'Guru', 'email' => 'widgetuang-guru@t.local', 'password' => 'password',
        ]);
        $guru->assignRole('guru');

        $bendahara = User::withoutGlobalScopes()->create([
            'tenant_id' => $super->tenant_id, 'name' => 'Bendahara', 'email' => 'widgetuang-bdh@t.local', 'password' => 'password',
        ]);
        $bendahara->assignRole('bendahara');

        // Guru: keempat widget keuangan tersembunyi (tutup kebocoran).
        Auth::login($guru);
        $this->assertFalse(KeuanganAreaChart::canView(), 'guru tak boleh lihat grafik keuangan');
        $this->assertFalse(KategoriPengeluaranChart::canView(), 'guru tak boleh lihat kategori pengeluaran');
        $this->assertFalse(TransaksiTerbaru::canView(), 'guru tak boleh lihat transaksi');
        $this->assertFalse(KeuanganOverviewWidget::canView(), 'guru tak boleh lihat ringkasan keuangan');

        // Bendahara: keempat widget keuangan tampil.
        Auth::login($bendahara);
        $this->assertTrue(KeuanganAreaChart::canView(), 'bendahara lihat grafik keuangan');
        $this->assertTrue(KategoriPengeluaranChart::canView());
        $this->assertTrue(TransaksiTerbaru::canView());
        $this->assertTrue(KeuanganOverviewWidget::canView());

        // Super_admin: tampil (bypass Gate::before).
        Auth::login($super);
        $this->assertTrue(KeuanganAreaChart::canView(), 'super_admin lihat semua widget');

        tenancy()->end();
    }
}
