<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** Kebijakan Privasi Sistem: halaman publik tenant + checkbox wajib di PPDB. */
class KebijakanPrivasiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'settings', 'calon_santri']);
        if (! Schema::connection('pgsql')->hasColumn('calon_santri', 'consent_kebijakan_at')) {
            Artisan::call('migrate', ['--force' => true]);
        }
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

    public function test_halaman_kebijakan_tampil_default_dan_bisa_dioverride(): void
    {
        [$tenant, $host, $server] = $this->boot('kp1');

        $res = $this->call('GET', "http://{$host}/kebijakan-privasi", [], [], [], $server);
        $res->assertOk();
        $res->assertSee('KEBIJAKAN PRIVASI SISTEM', false);
        $res->assertSee('contoh tulisan tangan', false);

        // Override redaksi per tenant.
        tenancy()->initialize($tenant);
        \App\Models\Setting::updateOrCreate(['key' => 'kebijakan_privasi_sistem'], ['value' => ['text' => 'REDAKSI KHUSUS PONDOK KAMI']]);
        tenancy()->end();

        $this->call('GET', "http://{$host}/kebijakan-privasi", [], [], [], $server)
            ->assertSee('REDAKSI KHUSUS PONDOK KAMI');
    }

    public function test_daftar_ppdb_wajib_centang_dan_consent_tercatat(): void
    {
        [$tenant, $host, $server] = $this->boot('kp2');
        tenancy()->initialize($tenant);
        $tenant->domains()->firstOrCreate(['domain' => $tenant->id]); // module route BySubdomain
        tenancy()->end();

        // Kontrak form publik singkat (PublicPpdbController): field camelCase.
        $payload = [
            'nama' => 'Calon Uji', 'jenisKelamin' => 'L', 'tempatLahir' => 'Sumedang',
            'tanggalLahir' => '2013-02-02',
            'namaWali' => 'Ayah Uji', 'noHpWali' => '081234567890', 'jenjangAsal' => 'SD/MI', 'asalSekolah' => 'MI Uji',
        ];

        // Tanpa centang -> ditolak.
        $this->from("http://{$host}/ppdb")
            ->post("http://{$host}/ppdb", $payload, $server)
            ->assertSessionHasErrors('setuju_kebijakan');

        // Dengan centang -> sukses + consent tercatat.
        $this->post("http://{$host}/ppdb", $payload + ['setuju_kebijakan' => '1'], $server)
            ->assertSessionDoesntHaveErrors();

        tenancy()->initialize($tenant);
        $calon = \Modules\PPDB\Models\CalonSantri::query()->where('nama', 'Calon Uji')->first();
        $this->assertNotNull($calon);
        $this->assertNotNull($calon->consent_kebijakan_at, 'Waktu persetujuan kebijakan tercatat.');
        tenancy()->end();
    }
}
