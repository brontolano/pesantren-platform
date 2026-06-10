<?php

namespace Tests\Feature;

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
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users']);
        if (! Schema::connection('pgsql')->hasColumn('users', 'ui_mode')) {
            Artisan::call('migrate', ['--force' => true]); // kolom baru pada tabel lama
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    public function test_default_advanced_dan_toggle_bolak_balik(): void
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

        // Default = advanced (perilaku lama tak berubah).
        $this->actingAs($user);
        $this->assertTrue(UiMode::advanced());

        // Toggle → simple.
        $this->post("http://{$host}/ui-mode/toggle", [], $server)->assertRedirect();
        $this->assertSame('simple', $user->fresh()->ui_mode);

        // Toggle lagi → advanced.
        $this->actingAs($user->fresh());
        $this->post("http://{$host}/ui-mode/toggle", [], $server)->assertRedirect();
        $this->assertSame('advanced', $user->fresh()->ui_mode);
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
}
