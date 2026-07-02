<?php

namespace Tests\Feature;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Pages\PusatModul;
use App\Models\Tenant;
use App\Support\ModuleRecommendationRegistry;
use Database\Seeders\ModuleRegistrySeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Issue #331 — Mode Salaf: preset onboarding minimalis. Penerapan preset menulis
 * tenant_modules dengan benar (inti salaf nyala, modul lanjutan mati), idempoten,
 * dan modul yang dimatikan hilang dari nav namun tetap bisa dipasang lagi.
 */
class ModeSalafPresetTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'model_has_roles', 'model_has_permissions', 'module_registry', 'tenant_modules', 'settings',
        ]);
        $this->truncatePpdbTables(['tenant_modules', 'module_registry', 'domains', 'tenants']);

        Config::set('tenancy.database.central_connection', 'pgsql');

        $this->tenant = $this->createRuntimeTenant('salaf', 'Pesantren Salaf');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        $this->seed(ModuleRegistrySeeder::class);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    /** Jalankan penerapan Mode Salaf tanpa lewat HTTP (bypass gerbang izin UI). */
    private function terapkan(): void
    {
        $page = new class extends PusatModul
        {
            // Bypass gerbang canAccess (diuji terpisah; fokus test = efek data).
            public static function canAccess(): bool
            {
                return true;
            }

            // Di luar konteks panel/Livewire: URL & redirect di-no-op-kan.
            public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null, bool $shouldGuessMissingParameters = false, ?string $configuration = null): string
            {
                return '/pusat-modul';
            }

            public function redirect($url, $navigate = false)
            {
                // no-op dalam test
            }
        };

        $page->terapkanModeSalaf();
    }

    /** @return array<string, bool> module_name => is_active */
    private function barisTenantModules(): array
    {
        return DB::connection('pgsql')->table('tenant_modules')
            ->where('tenant_id', $this->tenant->id)
            ->pluck('is_active', 'module_name')
            ->all();
    }

    private function aktifkan(string $module): void
    {
        DB::connection('pgsql')->table('tenant_modules')->updateOrInsert(
            ['tenant_id' => $this->tenant->id, 'module_name' => $module],
            ['is_active' => true, 'installed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        );
        app()->forgetInstance('__active_modules_'.$this->tenant->id);
        app()->forgetInstance('__configured_modules_'.$this->tenant->id);
    }

    public function test_terapkan_mode_salaf_nyalakan_inti_matikan_lanjutan(): void
    {
        // Tenant dengan beberapa modul lanjutan aktif (kondisi "kebanjiran menu").
        foreach (['ledger', 'pajak', 'mutu', 'crm', 'koperasi', 'ppdb'] as $m) {
            $this->aktifkan($m);
        }

        $this->terapkan();

        $rows = $this->barisTenantModules();

        foreach (ModuleRecommendationRegistry::MODE_SALAF_ON as $on) {
            $this->assertTrue($rows[$on] ?? false, "modul salaf '{$on}' harus aktif");
        }
        foreach (['ledger', 'pajak', 'mutu', 'crm', 'koperasi', 'ppdb'] as $off) {
            $this->assertFalse($rows[$off] ?? true, "modul lanjutan '{$off}' harus nonaktif");
        }
    }

    public function test_terapkan_mode_salaf_idempoten(): void
    {
        $this->aktifkan('pajak');

        $this->terapkan();
        $pertama = $this->barisTenantModules();

        // Terapkan lagi — hasil identik, tak ada baris ganda.
        $this->terapkan();
        $kedua = $this->barisTenantModules();

        $this->assertSame($pertama, $kedua);
        $this->assertSame(
            count($kedua),
            DB::connection('pgsql')->table('tenant_modules')->where('tenant_id', $this->tenant->id)->count(),
            'tidak boleh ada baris duplikat per (tenant, modul)',
        );
    }

    public function test_nav_mengikuti_mode_salaf(): void
    {
        $this->aktifkan('pajak');
        $this->aktifkan('mutu');

        $this->terapkan();

        $gate = new class
        {
            use ModuleGate;

            public function vis(string $key): bool
            {
                return self::moduleVisible($key);
            }
        };

        // Inti selalu tampil; modul salaf tampil; lanjutan tersembunyi.
        $this->assertTrue($gate->vis('kesantrian'));
        $this->assertTrue($gate->vis('asrama'));
        $this->assertTrue($gate->vis('tahfidz'));
        $this->assertFalse($gate->vis('pajak'));
        $this->assertFalse($gate->vis('mutu'));
        $this->assertFalse($gate->vis('ledger'));
    }

    public function test_modul_mati_bisa_diaktifkan_manual_lagi(): void
    {
        $this->aktifkan('mutu');
        $this->terapkan();

        $this->assertFalse($this->barisTenantModules()['mutu']);

        // Aktifkan manual lagi dari Pusat Modul (simulasi tulis ulang).
        $this->aktifkan('mutu');

        $this->assertTrue($this->barisTenantModules()['mutu']);
    }
}
