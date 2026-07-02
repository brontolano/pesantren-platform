<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\PPDB\Database\Seeders\PesertaRoleSeeder;
use Modules\PPDB\Jobs\SendPesertaOtpJob;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\UserOtp;
use Modules\PPDB\Services\PesertaAccountService;
use Modules\PPDB\Services\PpdbStatusTrackingService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PesertaAuthFlowTest extends TestCase
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
            'calon_santri',
            'user_otps',
        ]);

        $this->truncatePpdbTables([
            'user_otps',
            'calon_santri',
            'model_has_roles',
            'roles',
            'users',
            'domains',
            'tenants',
            'tenant_storage',
        ]);
    }

    public function test_public_registration_provisions_account_and_dispatches_activation_otp(): void
    {
        $suffix = Str::lower(Str::random(6));
        $tenantId = 'auth-'.$suffix;
        $host = $tenantId.'.localhost';
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Registrasi', $host);

        Queue::fake([SendPesertaOtpJob::class]);

        $submitResponse = $this->from("http://{$host}/ppdb")->post("http://{$host}/ppdb", [
            'nama' => 'Calon Auth',
            'tanggalLahir' => '2012-07-07',
            'jenisKelamin' => 'L',
            'jenjangAsal' => 'SD/MI',
            'asalSekolah' => 'SDIT Auth',
            'namaWali' => 'Ahmad Wali',
            'noHpWali' => '081234567890',
            'emailWali' => 'wali.auth@example.test',
            'setuju_kebijakan' => '1',
        ], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        tenancy()->initialize($tenant);
        $candidate = CalonSantri::query()->firstOrFail();
        $freshUser = User::query()->findOrFail($candidate->user_id);

        $this->assertTrue($freshUser->hasRole(PesertaRoleSeeder::ROLE));
        $this->assertTrue($freshUser->must_reset_password);
        tenancy()->end();

        $submitResponse->assertRedirect("http://{$host}/spmb/aktivasi?pendaftaran={$candidate->nomor_pendaftaran}");
        $submitResponse->assertSessionHas('success');
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
    }

    public function test_pending_participant_can_activate_account_with_otp(): void
    {
        $suffix = Str::lower(Str::random(6));
        $tenantId = 'activate-'.$suffix;
        $host = $tenantId.'.localhost';
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Aktivasi', $host);

        Queue::fake([SendPesertaOtpJob::class]);

        tenancy()->initialize($tenant);
        $candidate = CalonSantri::create([
            'tenant_id' => $tenant->id,
            'nomor_pendaftaran' => 'PPDB-AKTIVASI-001',
            'nama' => 'Calon Aktivasi',
            'tanggal_lahir' => '2012-02-02',
            'jenis_kelamin' => 'L',
            'status_pendaftaran' => 'pending',
            'program_diminati' => 'Tahfidz',
            'nama_ayah' => 'Wali Aktivasi',
            'no_hp_wali' => '081255500000',
            'email_wali' => 'aktivasi@example.test',
            'data_ortu' => [],
        ]);
        app(PesertaAccountService::class)->provisionAndSendActivationOtp($candidate);
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
        $activationResponse = $this->post("http://{$host}/spmb/aktivasi", [
            'pendaftaran' => 'PPDB-AKTIVASI-001',
            'kode' => $activationCode,
            'password' => 'passwordBaru123',
            'password_confirmation' => 'passwordBaru123',
        ], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $activationResponse->assertRedirect("http://{$host}/spmb");
        $activationResponse->assertSessionHas('success');
        $activatedCandidate = CalonSantri::query()->where('nomor_pendaftaran', 'PPDB-AKTIVASI-001')->firstOrFail();
        $mustResetPassword = (bool) User::query()->whereKey($activatedCandidate->user_id)->value('must_reset_password');
        $usedAt = UserOtp::query()
            ->where('user_id', $activatedCandidate->user_id)
            ->where('purpose', UserOtp::PURPOSE_AKTIVASI)
            ->latest('id')
            ->value('used_at');
        tenancy()->end();

        unset($activatedCandidate);

        $this->assertFalse($mustResetPassword);
        $this->assertNotNull($usedAt);
    }

    public function test_activated_participant_can_request_reset_otp_and_set_new_password(): void
    {
        Queue::fake([SendPesertaOtpJob::class]);

        $suffix = Str::lower(Str::random(6));
        $tenantId = 'reset-'.$suffix;
        $host = $tenantId.'.localhost';
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Reset', $host);

        tenancy()->initialize($tenant);
        $peserta = $this->makePeserta('Peserta Reset', 'peserta.reset@example.test', '628123450000', 'passwordLama123');
        $this->makeCandidate($tenant, $peserta, 'PPDB-RESET-001');
        tenancy()->end();

        $forgotResponse = $this->from("http://{$host}/spmb/lupa-password")->post("http://{$host}/spmb/lupa-password", [
            'identifier' => 'peserta.reset@example.test',
        ], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $forgotResponse->assertSessionHas('success');
        tenancy()->end();

        $resetCode = null;
        Queue::assertPushed(SendPesertaOtpJob::class, function (SendPesertaOtpJob $job) use ($tenant, &$resetCode): bool {
            if ($job->tenantId !== $tenant->id || $job->purpose !== UserOtp::PURPOSE_RESET) {
                return false;
            }

            $resetCode = $job->plainCode;

            return true;
        });
        $this->assertNotNull($resetCode);

        $resetResponse = $this->post("http://{$host}/spmb/lupa-password/reset", [
            'identifier' => '08123450000',
            'kode' => $resetCode,
            'password' => 'passwordReset456',
            'password_confirmation' => 'passwordReset456',
        ], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $resetResponse->assertRedirect("http://{$host}/spmb");
        $resetResponse->assertSessionHas('success');
        tenancy()->end();

        tenancy()->initialize($tenant);
        $resetUser = User::query()->findOrFail($peserta->id);
        tenancy()->end();

        $this->assertFalse($resetUser->must_reset_password);

        $this->post("http://{$host}/spmb/logout", [], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ])->assertRedirect("http://{$host}/spmb/login");
        tenancy()->end();

        $finalLogin = $this->post("http://{$host}/spmb/login", [
            'identifier' => '08123450000',
            'password' => 'passwordReset456',
        ], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $finalLogin->assertRedirect("http://{$host}/spmb");
    }

    public function test_activated_participant_can_login_using_registration_number(): void
    {
        $suffix = Str::lower(Str::random(6));
        $tenantId = 'login-'.$suffix;
        $host = $tenantId.'.localhost';
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Login', $host);

        tenancy()->initialize($tenant);
        $peserta = $this->makePeserta('Peserta Login', 'peserta.login@example.test', '628888880000', 'passwordLogin123');
        $candidate = $this->makeCandidate($tenant, $peserta, 'PPDB-LOGIN-001');
        tenancy()->end();

        $loginResponse = $this->from("http://{$host}/spmb/login")->post("http://{$host}/spmb/login", [
            'identifier' => $candidate->nomor_pendaftaran,
            'password' => 'passwordLogin123',
        ], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $loginResponse->assertRedirect("http://{$host}/spmb");
    }

    public function test_peserta_tenant_a_cannot_login_or_read_tracking_data_in_tenant_b(): void
    {
        $suffixA = Str::lower(Str::random(4));
        $suffixB = Str::lower(Str::random(4));
        $tenantAId = 'tenant-a-'.$suffixA;
        $tenantBId = 'tenant-b-'.$suffixB;
        $tenantAHost = $tenantAId.'.localhost';
        $tenantBHost = $tenantBId.'.localhost';

        $tenantA = $this->createProvisionedTenant($tenantAId, 'Tenant A', $tenantAHost);
        $tenantB = $this->createProvisionedTenant($tenantBId, 'Tenant B', $tenantBHost);

        tenancy()->initialize($tenantA);
        $pesertaA = $this->makePeserta('Peserta A', 'peserta-a@example.test', '628111111111', 'passwordA123');
        $candidateA = $this->makeCandidate($tenantA, $pesertaA, 'PPDB-A-001');
        $trackingTokenA = app(PpdbStatusTrackingService::class)->issueTrackingLink($candidateA)['tracking_token'];
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $this->makePeserta('Peserta B', 'peserta-b@example.test', '628222222222', 'passwordB123');
        tenancy()->end();

        $loginResponse = $this->from("http://{$tenantBHost}/spmb/login")->post("http://{$tenantBHost}/spmb/login", [
            'identifier' => 'peserta-a@example.test',
            'password' => 'passwordA123',
        ], [
            'HTTP_HOST' => $tenantBHost,
            'SERVER_NAME' => $tenantBHost,
        ]);

        $loginResponse->assertSessionHasErrors('identifier');

        $trackingResponse = $this->call('GET', "http://{$tenantBHost}/ppdb/tracking/{$trackingTokenA}", [], [], [], [
            'HTTP_HOST' => $tenantBHost,
            'SERVER_NAME' => $tenantBHost,
        ]);

        $trackingResponse->assertNotFound();
    }

    public function test_peserta_is_blocked_from_filament_admin_and_staff_is_blocked_from_spmb(): void
    {
        $suffix = Str::lower(Str::random(4));
        $tenantId = 'tenant-access-'.$suffix;
        $host = $tenantId.'.localhost';
        $tenant = $this->createProvisionedTenant($tenantId, 'Tenant Access', $host);

        tenancy()->initialize($tenant);
        $peserta = $this->makePeserta('Peserta Access', 'peserta-access@example.test', '628333333333', 'passwordAccess123');
        $this->makeCandidate($tenant, $peserta, 'PPDB-ACCESS-001');
        $staff = User::query()->where('email', "admin@{$tenantId}.localhost")->firstOrFail();
        tenancy()->end();

        $adminResponse = $this->actingAs($peserta)->get("http://{$host}/admin", [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);
        $adminResponse->assertForbidden();

        $spmbResponse = $this->actingAs($staff)->get("http://{$host}/spmb", [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);
        $spmbResponse->assertForbidden();
    }

    private function makePeserta(string $name, string $email, string $phone, string $password): User
    {
        $user = User::create([
            'tenant_id' => tenant()?->getTenantKey(),
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'must_reset_password' => false,
        ]);

        $user->assignRole(PesertaRoleSeeder::ROLE);

        return $user;
    }

    private function makeCandidate(Tenant $tenant, User $user, string $nomorPendaftaran): CalonSantri
    {
        return CalonSantri::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'nomor_pendaftaran' => $nomorPendaftaran,
            'nama' => $user->name,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status_pendaftaran' => 'pending',
            'program_diminati' => 'Reguler',
            'nama_ayah' => 'Wali '.$user->name,
            'no_hp_wali' => $user->phone,
            'email_wali' => $user->email,
            'data_ortu' => [],
        ]);
    }
}
