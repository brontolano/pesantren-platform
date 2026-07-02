<?php

namespace Tests\Feature;

use App\Http\Controllers\LauncherController;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AppLauncher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\PPDB\Jobs\SendPesertaOtpJob;
use Modules\PPDB\Models\UserOtp;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Alur auth peran publik "jemaah" (1-aplikasi, Fase 2b): registrasi mandiri
 * (No. WA + password via OTP WA) → aktivasi → login → lupa-password. Pola test
 * mengikuti PesertaAuthFlowTest: tenant DB fisik (createProvisionedTenant),
 * HTTP nyata, kode OTP plaintext ditangkap dari SendPesertaOtpJob (Queue::fake)
 * — TIDAK ada pengiriman WA nyata.
 */
class JemaahAuthFlowTest extends TestCase
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
            'permissions',
            'model_has_roles',
            'user_otps',
        ]);

        $this->truncatePpdbTables([
            'user_otps',
            'model_has_roles',
            'roles',
            'users',
            'domains',
            'tenants',
            'tenant_storage',
        ]);
    }

    /** @return array{0: Tenant, 1: string} */
    private function bootTenant(string $prefix): array
    {
        $host = $prefix.'-'.Str::lower(Str::random(6)).'.localhost';
        $tenantId = explode('.', $host)[0];
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Jemaah', $host);

        return [$tenant, $host];
    }

    /** @return array{0: string, 1: string} headers HTTP_HOST/SERVER_NAME */
    private function hostHeaders(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_daftar_membuat_akun_jemaah_dan_mengirim_otp(): void
    {
        [$tenant, $host] = $this->bootTenant('daftar');

        Queue::fake([SendPesertaOtpJob::class]);

        $resp = $this->from("http://{$host}/jemaah/daftar")->post("http://{$host}/jemaah/daftar", [
            'name' => 'Jemaah Satu',
            'phone' => '081234500001',
        ], $this->hostHeaders($host));

        $resp->assertStatus(302);
        $resp->assertRedirectContains('/jemaah/aktivasi');
        $resp->assertSessionHas('success');

        tenancy()->initialize($tenant);
        $user = User::query()->where('phone', '6281234500001')->first();
        $this->assertNotNull($user, 'akun jemaah harus dibuat dengan nomor ternormalisasi');
        $this->assertTrue($user->hasRole('jemaah'));
        $this->assertTrue((bool) $user->must_reset_password, 'belum aktif sampai OTP diverifikasi');
        tenancy()->end();

        $code = null;
        Queue::assertPushed(SendPesertaOtpJob::class, function (SendPesertaOtpJob $job) use ($tenant, &$code): bool {
            if ($job->tenantId !== $tenant->id || $job->purpose !== UserOtp::PURPOSE_JEMAAH_AKTIVASI) {
                return false;
            }
            $code = $job->plainCode;

            return true;
        });
        $this->assertNotNull($code);
    }

    public function test_daftar_nomor_duplikat_tidak_bocor_dan_tak_gandakan_akun(): void
    {
        [$tenant, $host] = $this->bootTenant('dup');

        Queue::fake([SendPesertaOtpJob::class]);

        $this->post("http://{$host}/jemaah/daftar", [
            'name' => 'Jemaah A',
            'phone' => '081234500002',
        ], $this->hostHeaders($host))->assertStatus(302);

        $second = $this->from("http://{$host}/jemaah/daftar")->post("http://{$host}/jemaah/daftar", [
            'name' => 'Jemaah B',
            'phone' => '081234500002',
        ], $this->hostHeaders($host));

        // Anti-enumerasi: TIDAK ada galat "sudah terdaftar"; respons seragam dengan
        // jalur sukses (302 ke aktivasi). Tidak ada akun kedua.
        $second->assertStatus(302);
        $second->assertSessionHasNoErrors();

        tenancy()->initialize($tenant);
        $this->assertSame(1, User::query()->where('phone', '6281234500002')->count());
        tenancy()->end();
    }

    public function test_daftar_nomor_milik_staf_tidak_bocor_dan_tak_buat_jemaah(): void
    {
        [$tenant, $host] = $this->bootTenant('staf');

        Queue::fake([SendPesertaOtpJob::class]);

        tenancy()->initialize($tenant);
        $staf = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Bu Bendahara',
            'phone' => '6281234500099',
            'password' => 'rahasiaStaf12',
            'must_reset_password' => false,
        ]);
        $staf->assignRole('bendahara');
        tenancy()->end();

        $resp = $this->from("http://{$host}/jemaah/daftar")->post("http://{$host}/jemaah/daftar", [
            'name' => 'Penyusup',
            'phone' => '081234500099',
        ], $this->hostHeaders($host));

        // Tak membocorkan bahwa nomor itu milik staf, tetapi juga TIDAK membuat
        // akun jemaah di atasnya & TIDAK mengirim OTP ke nomor staf.
        $resp->assertStatus(302);
        $resp->assertSessionHasNoErrors();
        Queue::assertNotPushed(SendPesertaOtpJob::class);

        tenancy()->initialize($tenant);
        $u = User::query()->where('phone', '6281234500099')->firstOrFail();
        $this->assertFalse($u->hasRole('jemaah'), 'akun staf tak boleh dapat role jemaah');
        $this->assertSame(1, User::query()->where('phone', '6281234500099')->count(), 'tak boleh ada akun kedua');
        tenancy()->end();
    }

    public function test_launcher_jemaah_render_beranda_bukan_redirect_loop(): void
    {
        [$tenant] = $this->bootTenant('loop');

        tenancy()->initialize($tenant);
        $jemaah = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Jemaah Loop',
            'phone' => '6281234500077',
            'password' => 'rahasiaLoop12',
            'must_reset_password' => false,
        ]);
        $jemaah->assignRole('jemaah');

        $this->actingAs($jemaah->fresh());
        $resp = app(LauncherController::class)();
        tenancy()->end();

        // Akar bug review: redirect('/') jemaah di domain 'sistem' → loop. Fix:
        // LauncherController merender Beranda untuk jemaah, BUKAN redirect.
        $this->assertNotInstanceOf(
            RedirectResponse::class,
            $resp,
            'jemaah harus dirender Beranda publik, bukan redirect (anti ERR_TOO_MANY_REDIRECTS)'
        );
    }

    public function test_aktivasi_kode_benar_mengaktifkan_dan_login(): void
    {
        [$tenant, $host] = $this->bootTenant('aktif');

        Queue::fake([SendPesertaOtpJob::class]);

        $this->post("http://{$host}/jemaah/daftar", [
            'name' => 'Jemaah Aktif',
            'phone' => '081234500003',
        ], $this->hostHeaders($host))->assertStatus(302);

        $code = $this->captureCode($tenant, UserOtp::PURPOSE_JEMAAH_AKTIVASI);

        $resp = $this->post("http://{$host}/jemaah/aktivasi", [
            'identifier' => '081234500003',
            'kode' => $code,
            'password' => 'rahasiaJemaah1',
            'password_confirmation' => 'rahasiaJemaah1',
        ], $this->hostHeaders($host));

        $resp->assertStatus(302);
        $this->assertAuthenticated();

        tenancy()->initialize($tenant);
        $user = User::query()->where('phone', '6281234500003')->firstOrFail();
        $this->assertFalse((bool) $user->must_reset_password);
        tenancy()->end();
    }

    public function test_aktivasi_kode_salah_gagal(): void
    {
        [$tenant, $host] = $this->bootTenant('salah');

        Queue::fake([SendPesertaOtpJob::class]);

        $this->post("http://{$host}/jemaah/daftar", [
            'name' => 'Jemaah Salah',
            'phone' => '081234500004',
        ], $this->hostHeaders($host))->assertStatus(302);

        $this->captureCode($tenant, UserOtp::PURPOSE_JEMAAH_AKTIVASI);

        $resp = $this->from("http://{$host}/jemaah/aktivasi")->post("http://{$host}/jemaah/aktivasi", [
            'identifier' => '081234500004',
            'kode' => '000000',
            'password' => 'rahasiaJemaah1',
            'password_confirmation' => 'rahasiaJemaah1',
        ], $this->hostHeaders($host));

        $resp->assertSessionHasErrors('kode');
        $this->assertGuest();

        tenancy()->initialize($tenant);
        $this->assertTrue((bool) User::query()->where('phone', '6281234500004')->value('must_reset_password'));
        tenancy()->end();
    }

    public function test_login_sebelum_aktivasi_ditolak(): void
    {
        [, $host] = $this->bootTenant('belum');

        Queue::fake([SendPesertaOtpJob::class]);

        $this->post("http://{$host}/jemaah/daftar", [
            'name' => 'Jemaah Belum',
            'phone' => '081234500005',
        ], $this->hostHeaders($host))->assertStatus(302);

        $resp = $this->from("http://{$host}/jemaah/login")->post("http://{$host}/jemaah/login", [
            'identifier' => '081234500005',
            'password' => 'apapunIni12',
        ], $this->hostHeaders($host));

        $resp->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    public function test_login_setelah_aktivasi_sukses(): void
    {
        [$tenant, $host] = $this->bootTenant('login');

        Queue::fake([SendPesertaOtpJob::class]);

        $this->post("http://{$host}/jemaah/daftar", [
            'name' => 'Jemaah Login',
            'phone' => '081234500006',
        ], $this->hostHeaders($host))->assertStatus(302);

        $code = $this->captureCode($tenant, UserOtp::PURPOSE_JEMAAH_AKTIVASI);

        $this->post("http://{$host}/jemaah/aktivasi", [
            'identifier' => '081234500006',
            'kode' => $code,
            'password' => 'rahasiaJemaah2',
            'password_confirmation' => 'rahasiaJemaah2',
        ], $this->hostHeaders($host))->assertStatus(302);

        // keluar dulu agar uji login bersih
        $this->post("http://{$host}/jemaah/logout", [], $this->hostHeaders($host));

        $resp = $this->from("http://{$host}/jemaah/login")->post("http://{$host}/jemaah/login", [
            'identifier' => '081234500006',
            'password' => 'rahasiaJemaah2',
        ], $this->hostHeaders($host));

        $resp->assertStatus(302);
        $resp->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_reset_password_via_otp(): void
    {
        [$tenant, $host] = $this->bootTenant('reset');

        Queue::fake([SendPesertaOtpJob::class]);

        // daftar + aktivasi
        $this->post("http://{$host}/jemaah/daftar", [
            'name' => 'Jemaah Reset',
            'phone' => '081234500007',
        ], $this->hostHeaders($host))->assertStatus(302);
        $aktivasiCode = $this->captureCode($tenant, UserOtp::PURPOSE_JEMAAH_AKTIVASI);
        $this->post("http://{$host}/jemaah/aktivasi", [
            'identifier' => '081234500007',
            'kode' => $aktivasiCode,
            'password' => 'passwordLama12',
            'password_confirmation' => 'passwordLama12',
        ], $this->hostHeaders($host))->assertStatus(302);
        $this->post("http://{$host}/jemaah/logout", [], $this->hostHeaders($host));

        // minta OTP reset
        Queue::fake([SendPesertaOtpJob::class]);
        $this->from("http://{$host}/jemaah/lupa-password")->post("http://{$host}/jemaah/lupa-password", [
            'identifier' => '081234500007',
        ], $this->hostHeaders($host))->assertSessionHas('success');

        $resetCode = $this->captureCode($tenant, UserOtp::PURPOSE_JEMAAH_RESET);

        $this->post("http://{$host}/jemaah/lupa-password/reset", [
            'identifier' => '081234500007',
            'kode' => $resetCode,
            'password' => 'passwordBaru34',
            'password_confirmation' => 'passwordBaru34',
        ], $this->hostHeaders($host))->assertStatus(302);

        $this->post("http://{$host}/jemaah/logout", [], $this->hostHeaders($host));

        // login pakai password baru
        $this->from("http://{$host}/jemaah/login")->post("http://{$host}/jemaah/login", [
            'identifier' => '081234500007',
            'password' => 'passwordBaru34',
        ], $this->hostHeaders($host))->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_jemaah_diblokir_dari_panel_admin(): void
    {
        [$tenant, $host] = $this->bootTenant('panel');

        tenancy()->initialize($tenant);
        $jemaah = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Jemaah Panel',
            'phone' => '6281234500008',
            'password' => 'rahasiaPanel12',
            'must_reset_password' => false,
        ]);
        $jemaah->assignRole('jemaah');
        $this->assertFalse($jemaah->isTenantStaff(), 'jemaah BUKAN staf');
        tenancy()->end();

        $this->actingAs($jemaah)
            ->get("http://{$host}/admin", $this->hostHeaders($host))
            ->assertForbidden();
    }

    public function test_applauncher_jemaah_ke_beranda(): void
    {
        [$tenant, $host] = $this->bootTenant('launch');

        tenancy()->initialize($tenant);
        $jemaah = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Jemaah Launch',
            'phone' => '6281234500009',
            'password' => 'rahasiaLaunch1',
            'must_reset_password' => false,
        ]);
        $jemaah->assignRole('jemaah');

        $dest = AppLauncher::primaryDestination($jemaah->fresh());
        tenancy()->end();

        $this->assertSame('redirect', $dest['kind']);
        $this->assertSame(url('/'), $dest['url']);
    }

    private function captureCode(Tenant $tenant, string $purpose): string
    {
        $code = null;
        Queue::assertPushed(SendPesertaOtpJob::class, function (SendPesertaOtpJob $job) use ($tenant, $purpose, &$code): bool {
            if ($job->tenantId !== $tenant->id || $job->purpose !== $purpose) {
                return false;
            }
            $code = $job->plainCode;

            return true;
        });
        $this->assertNotNull($code, "OTP purpose {$purpose} harus terkirim");

        return $code;
    }
}
