<?php

namespace Tests\Feature;

use App\Http\Controllers\KeuanganApiController;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\PembayaranService;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Models\SumberDana;
use Modules\Ledger\Services\BukuBesarService;
use Modules\Ledger\Services\LaporanService;
use Modules\Pajak\Database\Seeders\PajakSeeder;
use Modules\Pengadaan\Models\Belanja;
use Modules\Pengadaan\Services\BelanjaService;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PwaApiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurePpdbTestingDatabase();
        if (! Schema::connection('pgsql')->hasTable('belanja')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(): void
    {
        $tenant = $this->createRuntimeTenant('pwa', 'Pesantren PWA');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
        (new PajakSeeder)->run();
        (new RolesAndPermissionsSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->user = User::withoutGlobalScopes()->create([
            'name' => 'Bendahara', 'email' => 'b@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);
        $this->user->assignRole('bendahara');
    }

    private function req(string $method, array $data): Request
    {
        $request = Request::create('/x', $method, $data);
        $request->setUserResolver(fn () => $this->user);

        return $request;
    }

    public function test_api_belanja_hitung_pajak_dan_idempoten(): void
    {
        $this->boot();
        $sumber = SumberDana::where('kode_dana', '03')->value('id');
        $controller = new KeuanganApiController;

        $payload = [
            'client_ref' => 'pwa-belanja-1',
            'uraian' => 'Beli printer', 'jenis' => 'barang', 'dpp' => 3000000,
            'sumber_dana_id' => $sumber, 'vendor_npwp' => true,
        ];

        $res1 = $controller->belanja($this->req('POST', $payload), app(BelanjaService::class));
        $this->assertSame(201, $res1->getStatusCode());
        $body = $res1->getData(true);
        $this->assertEqualsWithDelta(330000, $body['ppn'], 0.01);
        $this->assertEqualsWithDelta(45000, $body['pph'], 0.01);

        // Idempoten: client_ref sama → tidak menggandakan.
        $controller->belanja($this->req('POST', $payload), app(BelanjaService::class));
        $this->assertSame(1, Belanja::count());
    }

    public function test_api_pemasukan_membuat_jurnal_otomatis(): void
    {
        $this->boot();
        $santri = Santri::create(['nis' => 'S-'.str()->random(4), 'nama' => 'A', 'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L']);
        $tagihan = Tagihan::create([
            'santri_id' => $santri->id, 'jenis_tagihan' => 'spp', 'nominal' => 200000,
            'nominal_akhir' => 200000, 'jatuh_tempo' => '2026-06-30', 'status' => 'belum_bayar',
        ]);

        $controller = new KeuanganApiController;
        $res = $controller->pemasukan($this->req('POST', ['tagihan_id' => $tagihan->id, 'nominal' => 200000]), app(PembayaranService::class));

        $this->assertSame(201, $res->getStatusCode());
        $this->assertTrue(Jurnal::where('ref_type', 'Pembayaran')->exists(), 'Pemasukan harus auto-jurnal.');
        $this->assertEqualsWithDelta(200000, app(BukuBesarService::class)->bku()['saldo_akhir'], 0.01);
    }

    public function test_api_saldo_dan_dashboard(): void
    {
        $this->boot();
        // posting pemasukan dulu
        $santri = Santri::create(['nis' => 'S-'.str()->random(4), 'nama' => 'B', 'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L']);
        $tagihan = Tagihan::create([
            'santri_id' => $santri->id, 'jenis_tagihan' => 'spp', 'nominal' => 500000,
            'nominal_akhir' => 500000, 'jatuh_tempo' => '2026-06-30', 'status' => 'belum_bayar',
        ]);
        $controller = new KeuanganApiController;
        $controller->pemasukan($this->req('POST', ['tagihan_id' => $tagihan->id, 'nominal' => 500000]), app(PembayaranService::class));

        $saldo = $controller->saldo(app(BukuBesarService::class))->getData(true);
        $this->assertEqualsWithDelta(500000, $saldo['saldo_kas'], 0.01);

        $dash = $controller->dashboardPimpinan(app(LaporanService::class), app(BukuBesarService::class))->getData(true);
        $this->assertEqualsWithDelta(500000, $dash['kas_konsolidasi'], 0.01);
        $this->assertEqualsWithDelta(500000, $dash['total_pendapatan'], 0.01);
        $this->assertEqualsWithDelta(500000, $dash['surplus_defisit'], 0.01);
    }
}
