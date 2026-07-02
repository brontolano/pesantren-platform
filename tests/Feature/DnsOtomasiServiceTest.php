<?php

namespace Tests\Feature;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DnsOtomasiService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * DNS custom domain 2 opsi (manual + otomasi Hostinger) — Refs #350 poin 4.
 */
class DnsOtomasiServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'dns.server_ip' => '187.77.116.167',
            'dns.hostinger.base_url' => 'https://developers.hostinger.com',
        ]);
    }

    /** Service dengan lookup A record yang di-mock (tanpa jaringan). */
    private function serviceDenganARecords(array $ips): DnsOtomasiService
    {
        return new class($ips) extends DnsOtomasiService
        {
            public function __construct(private array $ips) {}

            protected function aRecords(string $domain): array
            {
                return $this->ips;
            }
        };
    }

    // ---------- Opsi otomasi ----------

    public function test_tanpa_token_otomasi_nonaktif_dan_fallback_manual(): void
    {
        config(['dns.hostinger.token' => null]);
        $svc = new DnsOtomasiService;

        $this->assertFalse($svc->otomasiAktif());

        $hasil = $svc->buatARecord('pesantren.asy-syifaa.com');
        $this->assertFalse($hasil['ok']);
        $this->assertTrue($hasil['fallback_manual']);
        $this->assertStringContainsString('HOSTINGER_API_TOKEN', $hasil['pesan']);
        $this->assertStringContainsString('187.77.116.167', $hasil['pesan']);
    }

    public function test_sukses_buat_a_record_di_zona_induk(): void
    {
        config(['dns.hostinger.token' => 'token-test']);
        Http::fake([
            // Zona domain penuh tidak ada, zona induk ada.
            'developers.hostinger.com/api/dns/v1/zones/pesantren.asy-syifaa.com' => Http::response(['message' => 'not found'], 404),
            'developers.hostinger.com/api/dns/v1/zones/asy-syifaa.com' => Http::response(['zone' => []], 200),
        ]);

        $hasil = (new DnsOtomasiService)->buatARecord('pesantren.asy-syifaa.com');

        $this->assertTrue($hasil['ok']);
        $this->assertSame('asy-syifaa.com', $hasil['zona']);
        $this->assertStringContainsString('berhasil', $hasil['pesan']);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'PUT') {
                return false;
            }
            $data = $request->data();

            return str_contains($request->url(), '/api/dns/v1/zones/asy-syifaa.com')
                && $data['overwrite'] === false
                && $data['zone'][0]['name'] === 'pesantren'
                && $data['zone'][0]['type'] === 'A'
                && $data['zone'][0]['records'][0]['content'] === '187.77.116.167';
        });
    }

    public function test_zona_tak_ditemukan_fallback_instruksi_manual(): void
    {
        config(['dns.hostinger.token' => 'token-test']);
        Http::fake(['developers.hostinger.com/*' => Http::response(['message' => 'not found'], 404)]);

        $hasil = (new DnsOtomasiService)->buatARecord('pesantren.domainlain.id');

        $this->assertFalse($hasil['ok']);
        $this->assertTrue($hasil['fallback_manual']);
        $this->assertStringContainsString('tidak ditemukan di akun Hostinger', $hasil['pesan']);
        $this->assertStringContainsString('Arahkan A record', $hasil['pesan']);
    }

    public function test_error_jaringan_tidak_500_melainkan_pesan_ramah(): void
    {
        config(['dns.hostinger.token' => 'token-test']);
        Http::fake(function () {
            throw new ConnectionException('timeout');
        });

        $hasil = (new DnsOtomasiService)->buatARecord('pesantren.asy-syifaa.com');

        $this->assertFalse($hasil['ok']);
        $this->assertTrue($hasil['fallback_manual']);
        $this->assertStringContainsString('Gagal menghubungi API Hostinger', $hasil['pesan']);
    }

    // ---------- Opsi manual (Cek DNS) ----------

    public function test_cek_dns_benar(): void
    {
        $hasil = $this->serviceDenganARecords(['187.77.116.167'])->cekDns('pesantren.asy-syifaa.com');

        $this->assertTrue($hasil['ok']);
        $this->assertStringContainsString('sudah mengarah', $hasil['pesan']);
    }

    public function test_cek_dns_ip_salah(): void
    {
        $hasil = $this->serviceDenganARecords(['1.2.3.4'])->cekDns('pesantren.asy-syifaa.com');

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('bukan IP platform', $hasil['pesan']);
        $this->assertStringContainsString('187.77.116.167', $hasil['pesan']);
    }

    public function test_cek_dns_belum_resolve(): void
    {
        $hasil = $this->serviceDenganARecords([])->cekDns('belum-ada.asy-syifaa.com');

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('belum resolve', $hasil['pesan']);
    }

    // ---------- Helper UI ----------

    public function test_custom_domains_hanya_domain_bertitik(): void
    {
        // Tanpa event TenantCreated (hindari CREATE DATABASE fisik di test).
        $tenant = Tenant::withoutEvents(fn () => Tenant::create(['id' => 'dns-uji-350', 'name' => 'DNS Uji', 'is_active' => true]));
        $tenant->domains()->create(['domain' => 'dns-uji-350']);
        $tenant->domains()->create(['domain' => 'pesantren.dns-uji.com', 'type' => 'website']);

        $this->assertSame(['pesantren.dns-uji.com'], TenantResource::customDomains($tenant->fresh()->load('domains')));
    }

    public function test_halaman_daftar_tenant_render_tanpa_500_dengan_aksi_dns(): void
    {
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);

        $tenant = Tenant::withoutEvents(fn () => Tenant::create(['id' => 'dns-render-350', 'name' => 'DNS Render', 'is_active' => true]));
        $tenant->domains()->create(['domain' => 'pesantren.dns-render.com', 'type' => 'website']);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Super Admin',
            'email' => 'admin@dns-render-350.localhost',
            'password' => 'password',
            'tenant_id' => 'dns-render-350',
        ]);
        $user->assignRole('super_admin');

        $central = (array) config('tenancy.central_domains');
        $host = $central[0] ?? 'localhost';

        $response = $this->actingAs($user)->get("http://{$host}/admin/tenants");

        $this->assertLessThan(500, $response->getStatusCode(), 'Halaman daftar tenant (UI DNS domain) tidak boleh 500');
    }
}
