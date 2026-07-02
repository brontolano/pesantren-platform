<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\UiMode;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** Switch mode tampilan: toggle per user, default advanced, hanya visibilitas. */
class UiModeToggleTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'settings']);
        if (! Schema::connection('pgsql')->hasColumn('users', 'ui_mode')) {
            Artisan::call('migrate', ['--force' => true]); // kolom baru pada tabel lama
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_default_simple_dan_toggle_bolak_balik(): void
    {
        $tenant = $this->createRuntimeTenant('ui1', 'Pesantren UI');
        tenancy()->initialize($tenant);
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => true, 'data' => json_encode([]),
                'created_at' => now(), 'updated_at' => now()],
        );
        if (! Schema::connection('tenant')->hasColumn('users', 'ui_mode')) {
            Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true]);
        }
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Admin UI',
            'email' => 'ui1@mode.test', 'password' => 'password123',
        ]);
        tenancy()->end();

        $host = $tenant->id.'.localhost';
        $server = ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];

        // Default = simple untuk semua user (Mode Lengkap = opt-in).
        $this->actingAs($user);
        $this->assertTrue(UiMode::simple());

        // Toggle → advanced.
        $this->post("http://{$host}/ui-mode/toggle", [], $server)->assertRedirect();
        $this->assertSame('advanced', $user->fresh()->ui_mode);

        // Toggle lagi → simple.
        $this->actingAs($user->fresh());
        $this->post("http://{$host}/ui-mode/toggle", [], $server)->assertRedirect();
        $this->assertSame('simple', $user->fresh()->ui_mode);
    }

    public function test_guest_tidak_bisa_toggle(): void
    {
        $tenant = $this->createRuntimeTenant('ui2', 'Pesantren UI2');
        $host = $tenant->id.'.localhost';

        $this->post("http://{$host}/ui-mode/toggle", [], [
            'HTTP_HOST' => $host, 'SERVER_NAME' => $host,
        ])->assertRedirect(); // dialihkan ke login oleh middleware auth
        $this->assertGuest();
    }

    /**
     * gap-112: navAdvanced('ledger') sebagai SETELAN per-pesantren, nol regresi.
     *
     * (a) tanpa Setting allowlist & user simple → navAdvanced === advanced() === false (PERSIS sama, nol regresi);
     * (b) Setting ui.sederhana_tampilkan_ledger=true & user simple → navAdvanced === true (menu inti dipromosikan);
     * (c) user advanced → navAdvanced selalu true apapun setting.
     */
    public function test_nav_advanced_setelan_promosi_nol_regresi(): void
    {
        $tenant = $this->createRuntimeTenant('ui3', 'Pesantren UI3');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        if (! Schema::connection('tenant')->hasColumn('users', 'ui_mode')) {
            Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true]);
        }

        // ui_mode bukan kolom fillable (di-set lewat route toggle, bukan
        // mass-assign) → set langsung lalu save, meniru perilaku produksi.
        $simple = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Staf Simple',
            'email' => 'simple@mode.test', 'password' => 'password123',
        ]);
        $simple->ui_mode = 'simple';
        $simple->save();

        $advanced = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Admin Advanced',
            'email' => 'advanced@mode.test', 'password' => 'password123',
        ]);
        $advanced->ui_mode = 'advanced';
        $advanced->save();

        // (a) Tak ada Setting allowlist + user simple → identik dgn perilaku lama.
        $this->actingAs($simple);
        $this->assertTrue(UiMode::simple());
        $this->assertFalse(UiMode::advanced());
        $this->assertFalse(UiMode::navAdvanced('ledger'));
        $this->assertSame(UiMode::advanced(), UiMode::navAdvanced('ledger')); // NOL REGRESI
        // Kunci lain juga tetap tersembunyi tanpa setting (allowlist semua).
        foreach (UiMode::NAV_KEYS as $key) {
            $this->assertFalse(UiMode::navAdvanced($key));
            $this->assertSame(UiMode::advanced(), UiMode::navAdvanced($key));
        }
        // Kunci di luar allowlist tak pernah promosi.
        $this->assertFalse(UiMode::navAdvanced('kunci_ngawur'));

        // (b) Setting ui.sederhana_tampilkan_ledger=true + user simple → muncul.
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'ui.sederhana_tampilkan_ledger',
            'value' => true,
        ]);
        $this->assertTrue(UiMode::simple());
        $this->assertTrue(UiMode::navAdvanced('ledger'));
        // Kunci lain yang belum disetel tetap tersembunyi (granular per-kunci).
        $this->assertFalse(UiMode::navAdvanced('pajak'));

        // (c) User advanced → navAdvanced selalu true apapun setting.
        $this->actingAs($advanced);
        $this->assertTrue(UiMode::advanced());
        foreach (UiMode::NAV_KEYS as $key) {
            $this->assertTrue(UiMode::navAdvanced($key));
        }

        tenancy()->end();
    }
}
