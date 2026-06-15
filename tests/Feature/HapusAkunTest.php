<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Halaman publik Penghapusan Akun & Data (/hapus-akun) — wajib Google Play.
 * Tenant-aware: redaksi default tampil, bisa di-override per pesantren, dan
 * versi central (pesantren.online) berdiri sendiri. Pola mengikuti
 * {@see KebijakanPrivasiTest}.
 */
class HapusAkunTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'settings']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): array
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        $host = $tenant->id.'.localhost';
        tenancy()->initialize($tenant);
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => true, 'data' => json_encode([]),
             'created_at' => now(), 'updated_at' => now()],
        );
        tenancy()->end();

        return [$tenant, $host, ['HTTP_HOST' => $host, 'SERVER_NAME' => $host]];
    }

    public function test_halaman_hapus_akun_tampil_default_dan_bisa_dioverride(): void
    {
        [$tenant, $host, $server] = $this->boot('ha1');

        $res = $this->call('GET', "http://{$host}/hapus-akun", [], [], [], $server);
        $res->assertOk();
        // Catatan: '&' di-escape Blade jadi '&amp;' di <pre>, jadi asersi pakai
        // potongan tanpa ampersand.
        $res->assertSee('PENGHAPUSAN AKUN', false);
        $res->assertSee('SIAPA YANG DAPAT MENGAJUKAN', false);
        $res->assertSee('Kanal Permintaan', false);
        // Tautan balik ke Kebijakan Privasi.
        $res->assertSee('/kebijakan-privasi', false);

        // Override redaksi per tenant (sama mekanisme dgn kebijakan privasi).
        tenancy()->initialize($tenant);
        \App\Models\Setting::updateOrCreate(
            ['key' => 'penghapusan_akun_sistem'],
            ['value' => ['text' => 'REDAKSI HAPUS KHUSUS PONDOK KAMI']],
        );
        tenancy()->end();

        $this->call('GET', "http://{$host}/hapus-akun", [], [], [], $server)
            ->assertSee('REDAKSI HAPUS KHUSUS PONDOK KAMI');
    }

    public function test_kebijakan_privasi_menautkan_ke_hapus_akun(): void
    {
        [, $host, $server] = $this->boot('ha2');

        $this->call('GET', "http://{$host}/kebijakan-privasi", [], [], [], $server)
            ->assertOk()
            ->assertSee('/hapus-akun', false);
    }

    public function test_versi_central_berdiri_sendiri(): void
    {
        // Tanpa tenancy aktif → controller mengembalikan view central generik.
        $html = view('legal.account-deletion')->render();
        $this->assertStringContainsString('Penghapusan Akun', $html);
        $this->assertStringContainsString('/hapus-akun', $html);
    }
}
