<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\Kesantrian\Models\Santri;
use Modules\PPDB\Jobs\SendPesertaOtpJob;
use Modules\PPDB\Models\UserOtp;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PortalWaliAuthFlowTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants',
            'domains',
            'tenant_storage',
            'users',
            'roles',
            'model_has_roles',
            'santri',
            'access_wali',
            'user_otps',
        ]);

        $this->truncatePpdbTables([
            'user_otps',
            'access_wali',
            'santri',
            'model_has_roles',
            'roles',
            'users',
            'domains',
            'tenants',
            'tenant_storage',
        ]);
    }

    public function test_guest_wali_is_redirected_to_portal_wali_login(): void
    {
        $suffix = Str::lower(Str::random(4));
        $tenantId = 'wali-guest-'.$suffix;
        $host = $tenantId.'.localhost';

        $this->createProvisionedTenant($tenantId, 'Pesantren Wali Guest', $host);

        $this->get("http://{$host}/wali", [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ])->assertRedirect("http://{$host}/wali/login");
    }

    public function test_wali_can_self_activate_from_active_santri_phone_and_login(): void
    {
        Queue::fake([SendPesertaOtpJob::class]);

        $suffix = Str::lower(Str::random(4));
        $tenantId = 'wali-activate-'.$suffix;
        $host = $tenantId.'.localhost';
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Wali Aktif', $host);

        tenancy()->initialize($tenant);
        Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => 'WALI-001',
            'nama' => 'Santri Aktivasi',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'nama_ayah' => 'Ayah Aktivasi',
            'no_hp_wali' => '081234567890',
            'email_wali' => 'ayah.aktivasi@example.test',
        ]);
        tenancy()->end();

        $resendResponse = $this->from("http://{$host}/wali/aktivasi")->post("http://{$host}/wali/aktivasi/kirim-ulang", [
            'identifier' => '081234567890',
        ], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $resendResponse->assertSessionHas('success');

        tenancy()->initialize($tenant);
        $wali = User::query()->where('email', 'ayah.aktivasi@example.test')->firstOrFail();
        $this->assertTrue($wali->hasRole('wali'));
        $this->assertTrue($wali->must_reset_password);
        $this->assertSame('6281234567890', $wali->phone);
        tenancy()->end();

        $activationCode = null;
        Queue::assertPushed(SendPesertaOtpJob::class, function (SendPesertaOtpJob $job) use ($tenant, &$activationCode): bool {
            if ($job->tenantId !== $tenant->id || $job->purpose !== UserOtp::PURPOSE_AKTIVASI) {
                return false;
            }

            $activationCode = $job->plainCode;

            return true;
        });
        $this->assertNotNull($activationCode);

        $activationResponse = $this->post("http://{$host}/wali/aktivasi", [
            'identifier' => '081234567890',
            'kode' => $activationCode,
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $activationResponse->assertRedirect("http://{$host}/wali");

        tenancy()->initialize($tenant);
        $freshWali = User::query()->findOrFail($wali->id);
        tenancy()->end();

        $this->assertFalse($freshWali->must_reset_password);

        $loginResponse = $this->post("http://{$host}/wali/login", [
            'identifier' => 'ayah.aktivasi@example.test',
            'password' => 'PasswordBaru123',
        ], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $loginResponse->assertRedirect("http://{$host}/wali");
    }

    public function test_activated_wali_can_request_reset_otp_and_set_new_password(): void
    {
        Queue::fake([SendPesertaOtpJob::class]);

        $suffix = Str::lower(Str::random(4));
        $tenantId = 'wali-reset-'.$suffix;
        $host = $tenantId.'.localhost';
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Wali Reset', $host);

        tenancy()->initialize($tenant);
        $wali = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Wali Reset',
            'email' => 'wali.reset@example.test',
            'phone' => '6281222200000',
            'password' => 'PasswordLama123',
            'must_reset_password' => false,
        ]);
        $wali->assignRole('wali');
        Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => 'WALI-002',
            'nama' => 'Santri Reset',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'P',
            'status' => 'aktif',
            'nama_ibu' => 'Ibu Reset',
            'no_hp_wali' => '081222200000',
            'email_wali' => 'wali.reset@example.test',
            'wali_user_id' => $wali->id,
        ]);
        tenancy()->end();

        $requestResponse = $this->from("http://{$host}/wali/lupa-password")->post("http://{$host}/wali/lupa-password", [
            'identifier' => '081222200000',
        ], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $requestResponse->assertSessionHas('success');

        $resetCode = null;
        Queue::assertPushed(SendPesertaOtpJob::class, function (SendPesertaOtpJob $job) use ($tenant, &$resetCode): bool {
            if ($job->tenantId !== $tenant->id || $job->purpose !== UserOtp::PURPOSE_RESET) {
                return false;
            }

            $resetCode = $job->plainCode;

            return true;
        });
        $this->assertNotNull($resetCode);

        $resetResponse = $this->post("http://{$host}/wali/lupa-password/reset", [
            'identifier' => 'wali.reset@example.test',
            'kode' => $resetCode,
            'password' => 'PasswordReset456',
            'password_confirmation' => 'PasswordReset456',
        ], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $resetResponse->assertRedirect("http://{$host}/wali");

        $loginResponse = $this->post("http://{$host}/wali/login", [
            'identifier' => '081222200000',
            'password' => 'PasswordReset456',
        ], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $loginResponse->assertRedirect("http://{$host}/wali");
    }
}
