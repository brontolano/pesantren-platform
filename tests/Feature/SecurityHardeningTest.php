<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\PPDB\Jobs\SendPesertaOtpJob;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Pengerasan keamanan red-team batch-1:
 *  - HIGH-002  : header keamanan global (nosniff/Referrer-Policy/HSTS).
 *  - AUTH-1    : token Sanctum punya kedaluwarsa (config).
 *  - RATE-001/AUTH-8 : anggaran harian OTP WhatsApp jemaah per tenant.
 */
class SecurityHardeningTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'tenant_storage', 'users', 'roles', 'permissions',
            'model_has_roles', 'settings', 'user_otps',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    /** @return array{0: Tenant, 1: string} */
    private function bootTenant(): array
    {
        $host = 'sec-'.Str::lower(Str::random(6)).'.localhost';
        $tenantId = explode('.', $host)[0];
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Uji', $host);

        $tenant->run(function () {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $perm = Permission::firstOrCreate(['name' => 'komentar']);
            Role::firstOrCreate(['name' => 'jemaah'])->givePermissionTo($perm);
        });

        return [$tenant, $host];
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_header_keamanan_terpasang(): void
    {
        [, $host] = $this->bootTenant();

        $resp = $this->getJson("http://{$host}/api/v1/jadwal-sholat", $this->headers($host));

        $resp->assertOk();
        $resp->assertHeader('X-Content-Type-Options', 'nosniff');
        $resp->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        // HSTS hanya pada koneksi aman (https) — tak diuji di klien http test.
    }

    public function test_sanctum_kedaluwarsa_dikonfigurasi(): void
    {
        $this->assertGreaterThan(
            0,
            (int) config('sanctum.expiration'),
            'Token Sanctum WAJIB punya kedaluwarsa (bukan null/selamanya).',
        );
    }

    public function test_anggaran_otp_harian_membatasi_kirim(): void
    {
        [$tenant, $host] = $this->bootTenant();
        config(['services.jemaah.otp_daily_budget' => 1]); // hanya 1 OTP/hari
        Queue::fake([SendPesertaOtpJob::class]);

        // Pendaftaran #1 → OTP terkirim (budget 0→1).
        $this->postJson("http://{$host}/api/v1/auth/daftar", [
            'name' => 'Warga Satu', 'phone' => '081234500001',
        ], $this->headers($host))->assertOk();

        // Pendaftaran #2 (nomor beda) → anggaran habis → OTP TIDAK dikirim (senyap, anti-enum).
        $this->postJson("http://{$host}/api/v1/auth/daftar", [
            'name' => 'Warga Dua', 'phone' => '081234500002',
        ], $this->headers($host))->assertOk();

        Queue::assertPushedTimes(SendPesertaOtpJob::class, 1);
    }
}
