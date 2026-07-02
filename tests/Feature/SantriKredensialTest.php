<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\SantriAccountService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** Kredensial App Santri: resetPin buat PIN 6 digit yang bisa dipakai login NIS+PIN. */
class SantriKredensialTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'santri', 'roles', 'model_has_roles']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function bootSantri(string $prefix): array
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => true, 'data' => json_encode([]),
                'created_at' => now(), 'updated_at' => now()],
        );
        if (! DB::connection('tenant')->table('roles')->where('name', 'santri')->exists()) {
            DB::connection('tenant')->table('roles')->insert([
                'name' => 'santri', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $santri = Santri::create([
            'nama' => 'Santri Kred', 'nis' => 'NIS-'.$prefix, 'status' => 'aktif',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L',
        ]);

        return [$tenant, $santri];
    }

    public function test_reset_pin_menghasilkan_pin_6_digit_yang_bisa_login(): void
    {
        [$tenant, $santri] = $this->bootSantri('kred1');
        $svc = app(SantriAccountService::class);

        [$user, $pin] = $svc->resetPin($santri);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $pin, 'PIN harus 6 digit numerik.');
        $this->assertNotNull($santri->fresh()->user_id, 'Akun santri dibuat & tertaut.');
        $this->assertTrue($user->hasRole('santri'));

        // PIN tersimpan ter-hash & cocok untuk login.
        $this->assertTrue(Hash::check($pin, $user->fresh()->password));

        // Login App Santri via NIS + PIN berhasil.
        $host = $tenant->id.'.localhost';
        tenancy()->end();
        $res = $this->post("http://{$host}/app/login", ['nis' => $santri->nis, 'password' => $pin], [
            'HTTP_HOST' => $host, 'SERVER_NAME' => $host,
        ]);
        $res->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_reset_pin_idempoten_provision_tidak_gandakan_akun(): void
    {
        [, $santri] = $this->bootSantri('kred2');
        $svc = app(SantriAccountService::class);

        [$u1] = $svc->resetPin($santri);
        [$u2, $pin2] = $svc->resetPin($santri->fresh());

        // Akun sama (tak dobel), PIN berganti.
        $this->assertSame($u1->id, $u2->id);
        $this->assertSame(1, User::withoutGlobalScopes()->where('tenant_id', $santri->tenant_id)->whereHas('roles', fn ($q) => $q->where('name', 'santri'))->count());
        $this->assertMatchesRegularExpression('/^\d{6}$/', $pin2);
        tenancy()->end();
    }
}
