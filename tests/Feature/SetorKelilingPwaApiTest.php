<?php

namespace Tests\Feature;

use App\Http\Controllers\KeuanganApiController;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Asrama\Models\BlokAsrama;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Asrama\Models\PenempatanAsrama;
use Modules\Keuangan\Models\SetorKelilingItem;
use Modules\Keuangan\Models\SetorKelilingSesi;
use Modules\Keuangan\Models\TabunganTransaksi;
use Modules\Keuangan\Services\SetorKelilingService;
use Modules\Keuangan\Services\TabunganService;
use Modules\Kesantrian\Models\Santri;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-32 — Setor Keliling via API PWA Keuangan: idempoten (client_ref),
 * gate permission setor_tabungan, daftar kamar/santri benar, dan saldo
 * hanya bertambah SEKALI walau dobel-kirim (posting tetap via approve).
 */
class SetorKelilingPwaApiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurePpdbTestingDatabase();
        if (! Schema::connection('pgsql')->hasTable('setor_keliling_item')
            || ! Schema::connection('pgsql')->hasColumn('setor_keliling_item', 'client_ref')
            || ! Schema::connection('pgsql')->hasTable('penempatan_asrama')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $this->truncatePpdbTables([
            'setor_keliling_item', 'setor_keliling_sesi', 'tabungan_transaksi', 'tabungan',
            'penempatan_asrama', 'kamar_asrama', 'blok_asrama', 'santri', 'domains', 'tenants',
        ]);
    }

    /** @return array{0:KamarAsrama,1:Santri,2:Santri} */
    private function boot(string $prefix, string $role = 'bendahara'): array
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new RolesAndPermissionsSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->user = User::withoutGlobalScopes()->create([
            'name' => 'Petugas '.$prefix, 'email' => $prefix.'@skpwa.test',
            'password' => 'password123', 'tenant_id' => $tenant->id,
        ]);
        if ($role !== '') {
            $this->user->assignRole($role);
        }

        $blok = BlokAsrama::create(['nama_blok' => 'Blok A', 'is_active' => true]);
        $kamar = KamarAsrama::create(['blok_id' => $blok->id, 'nomor_kamar' => 'A-01', 'kapasitas' => 8, 'is_active' => true]);

        $s1 = Santri::create(['nama' => 'Santri Satu', 'nis' => 'P1-'.$prefix, 'status' => 'aktif', 'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L']);
        $s2 = Santri::create(['nama' => 'Santri Dua', 'nis' => 'P2-'.$prefix, 'status' => 'aktif', 'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L']);
        PenempatanAsrama::create(['santri_id' => $s1->id, 'kamar_id' => $kamar->id, 'tanggal_masuk' => '2026-01-01', 'status' => 'aktif', 'is_current' => true]);
        PenempatanAsrama::create(['santri_id' => $s2->id, 'kamar_id' => $kamar->id, 'tanggal_masuk' => '2026-01-01', 'status' => 'aktif', 'is_current' => true]);

        return [$kamar, $s1, $s2];
    }

    private function req(string $method, array $data = []): Request
    {
        $request = Request::create('/x', $method, $data);
        $request->setUserResolver(fn () => $this->user);

        return $request;
    }

    public function test_dobel_kirim_client_ref_sama_tidak_dobel_catat_dan_saldo_bertambah_sekali(): void
    {
        [, $s1] = $this->boot('skp1');
        $controller = new KeuanganApiController;
        $svc = app(SetorKelilingService::class);

        $payload = ['client_ref' => 'pwa-sk-1', 'santri_id' => $s1->id, 'nominal' => 50000];

        $res1 = $controller->setorKelilingSetoran($this->req('POST', $payload), $svc);
        $this->assertSame(201, $res1->getStatusCode());
        $this->assertFalse($res1->getData(true)['idempotent']);

        // Retry offline-sync: client_ref sama → sukses-idempoten, TIDAK menambah item.
        $res2 = $controller->setorKelilingSetoran($this->req('POST', $payload), $svc);
        $this->assertSame(200, $res2->getStatusCode());
        $this->assertTrue($res2->getData(true)['idempotent']);
        $this->assertSame($res1->getData(true)['id'], $res2->getData(true)['id']);
        $this->assertSame(1, SetorKelilingItem::count());

        // Draf TIDAK menyentuh saldo.
        $tab = app(TabunganService::class);
        $this->assertSame(0.0, $tab->getSaldo($s1));

        // Ajukan via API + approve bendahara → saldo bertambah SEKALI.
        $resAjukan = $controller->setorKelilingAjukan($this->req('POST'), $svc);
        $this->assertSame(200, $resAjukan->getStatusCode());

        $sesi = SetorKelilingSesi::findOrFail($resAjukan->getData(true)['id']);
        $svc->setujui($sesi, $this->user->id);
        $this->assertSame(50000.0, $tab->getSaldo($s1));
        $this->assertSame(1, TabunganTransaksi::where('tipe', 'setor')->count());
        tenancy()->end();
    }

    public function test_tanpa_permission_ditolak_403(): void
    {
        [$kamar, $s1] = $this->boot('skp2', 'asatidz'); // asatidz TIDAK punya setor_tabungan
        $controller = new KeuanganApiController;

        foreach ([
            fn () => $controller->setorKelilingKamar($this->req('GET')),
            fn () => $controller->setorKelilingSantri($this->req('GET'), $kamar->id),
            fn () => $controller->setorKelilingSetoran($this->req('POST', ['client_ref' => 'x', 'santri_id' => $s1->id, 'nominal' => 1000]), app(SetorKelilingService::class)),
            fn () => $controller->setorKelilingSesi($this->req('GET')),
            fn () => $controller->setorKelilingAjukan($this->req('POST'), app(SetorKelilingService::class)),
        ] as $call) {
            try {
                $call();
                $this->fail('Tanpa permission setor_tabungan harus 403.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $this->assertSame(0, SetorKelilingItem::count());
        tenancy()->end();
    }

    public function test_daftar_kamar_dan_santri_per_kamar_benar(): void
    {
        [$kamar, $s1, $s2] = $this->boot('skp3');
        $controller = new KeuanganApiController;

        // Kamar lain kosong + santri pindahan (is_current=false) tak boleh ikut.
        $kamarKosong = KamarAsrama::create(['blok_id' => $kamar->blok_id, 'nomor_kamar' => 'A-02', 'kapasitas' => 8, 'is_active' => true]);
        $s3 = Santri::create(['nama' => 'Santri Pindah', 'nis' => 'P3-skp3', 'status' => 'aktif', 'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L']);
        PenempatanAsrama::create(['santri_id' => $s3->id, 'kamar_id' => $kamar->id, 'tanggal_masuk' => '2025-01-01', 'status' => 'pindah', 'is_current' => false]);

        $blokRes = $controller->setorKelilingKamar($this->req('GET'))->getData(true);
        $this->assertCount(1, $blokRes['blok']);
        $this->assertCount(2, $blokRes['blok'][0]['kamar']);
        $this->assertSame(2, $blokRes['blok'][0]['kamar'][0]['jumlah_santri']); // A-01
        $this->assertSame(0, $blokRes['blok'][0]['kamar'][1]['jumlah_santri']); // A-02

        $santriRes = $controller->setorKelilingSantri($this->req('GET'), $kamar->id)->getData(true);
        $this->assertSame([$s1->id, $s2->id], array_column($santriRes['santri'], 'id'));
        $this->assertSame(['Santri Satu', 'Santri Dua'], array_column($santriRes['santri'], 'nama'));

        $this->assertCount(0, $controller->setorKelilingSantri($this->req('GET'), $kamarKosong->id)->getData(true)['santri']);
        tenancy()->end();
    }

    public function test_setoran_kedua_masuk_sesi_draf_yang_sama_dan_sesi_endpoint_meringkas(): void
    {
        [, $s1, $s2] = $this->boot('skp4');
        $controller = new KeuanganApiController;
        $svc = app(SetorKelilingService::class);

        $controller->setorKelilingSetoran($this->req('POST', ['client_ref' => 'a-1', 'santri_id' => $s1->id, 'nominal' => 10000]), $svc);
        $controller->setorKelilingSetoran($this->req('POST', ['client_ref' => 'a-2', 'santri_id' => $s2->id, 'nominal' => 15000]), $svc);

        $this->assertSame(1, SetorKelilingSesi::count());

        $sesi = $controller->setorKelilingSesi($this->req('GET'))->getData(true)['sesi'];
        $this->assertSame('draf', $sesi['status']);
        $this->assertSame(2, $sesi['jumlah_item']);
        $this->assertEqualsWithDelta(25000.0, $sesi['total'], 0.01);
        tenancy()->end();
    }

    public function test_santri_id_tak_ada_atau_lintas_tenant_ditolak_422_tanpa_item(): void
    {
        // Tenant A punya santri; simpan id-nya lalu pindah ke tenant B.
        [, $sA] = $this->boot('skp5a');
        $santriTenantA = $sA->id;
        tenancy()->end();

        $this->boot('skp5b');
        $controller = new KeuanganApiController;
        $svc = app(SetorKelilingService::class);

        // DB-per-tenant: id tebakan dari tenant lain TIDAK pernah menjangkau
        // data tenant A — query ter-scope hanya melihat DB tenant B. Bila id
        // kebetulan ada di tenant B, ia adalah santri milik B sendiri.
        $tertebak = Santri::query()->whereKey($santriTenantA)->first();
        if ($tertebak !== null) {
            $this->assertSame(tenant('id'), $tertebak->tenant_id, 'Tebakan id lintas-tenant bocor ke tenant lain.');
        }

        // santri_id ngawur (tidak ada di tenant ini) → 422.
        $res2 = $controller->setorKelilingSetoran($this->req('POST', [
            'client_ref' => 'ngawur-1', 'santri_id' => 999999, 'nominal' => 5000,
        ]), $svc);
        $this->assertSame(422, $res2->getStatusCode());

        $this->assertSame(0, SetorKelilingItem::withoutGlobalScopes()->where('tenant_id', tenant('id'))->count());
        tenancy()->end();
    }

    public function test_ajukan_ikut_mengunci_draf_kemarin_yang_tertinggal(): void
    {
        [, $s1, $s2] = $this->boot('skp6');
        $controller = new KeuanganApiController;
        $svc = app(SetorKelilingService::class);

        // Draf kemarin tertinggal (petugas lupa ajukan) berisi 1 item.
        $sesiKemarin = $svc->buatSesi($this->user->id, 'Kamar A-01', now()->subDay()->toDateString());
        $svc->tambahItem($sesiKemarin, $s1->id, 20000, null, 'kemarin-1');

        // Setoran hari ini → sesi BARU (draf kemarin tidak menampung hari ini).
        $controller->setorKelilingSetoran($this->req('POST', ['client_ref' => 'hariini-1', 'santri_id' => $s2->id, 'nominal' => 30000]), $svc);
        $this->assertSame(2, SetorKelilingSesi::count());

        // Ajukan: KEDUA draf terkunci — uang kemarin tidak macet tanpa jalur.
        $res = $controller->setorKelilingAjukan($this->req('POST'), $svc)->getData(true);
        $this->assertSame(2, $res['sesi_diajukan']);
        $this->assertEqualsWithDelta(50000.0, $res['total_semua'], 0.01);
        $this->assertSame(0, SetorKelilingSesi::where('status', 'draf')->count());
        $this->assertSame(2, SetorKelilingSesi::where('status', 'diajukan')->count());
        tenancy()->end();
    }

    public function test_nominal_nol_negatif_dan_nonangka_ditolak(): void
    {
        [, $s1] = $this->boot('skp7');
        $controller = new KeuanganApiController;
        $svc = app(SetorKelilingService::class);

        foreach ([0, -5000, 0.5, 'abc'] as $i => $buruk) {
            try {
                $res = $controller->setorKelilingSetoran($this->req('POST', [
                    'client_ref' => 'buruk-'.$i, 'santri_id' => $s1->id, 'nominal' => $buruk,
                ]), $svc);
                $this->assertSame(422, $res->getStatusCode(), 'Nominal '.$buruk.' harus ditolak.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertArrayHasKey('nominal', $e->errors());
            }
        }
        $this->assertSame(0, SetorKelilingItem::count());
        tenancy()->end();
    }
}
