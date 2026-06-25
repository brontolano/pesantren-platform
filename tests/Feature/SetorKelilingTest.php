<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\SetorKelilingSesi;
use Modules\Keuangan\Models\TabunganTransaksi;
use Modules\Keuangan\Services\SetorKelilingService;
use Modules\Keuangan\Services\TabunganService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** Setor keliling: draf tak menyentuh saldo, approve posting via TabunganService, idempoten. */
class SetorKelilingTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'tabungan', 'tabungan_transaksi',
            'setor_keliling_sesi', 'setor_keliling_item',
        ]);
        $this->truncatePpdbTables([
            'setor_keliling_item', 'setor_keliling_sesi', 'tabungan_transaksi', 'tabungan',
            'santri', 'domains', 'tenants',
        ]);
    }

    /** @return array{0:SetorKelilingService,1:Santri,2:Santri,3:User} */
    private function boot(string $prefix): array
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => true, 'data' => json_encode([]),
                'created_at' => now(), 'updated_at' => now()],
        );

        $petugas = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Petugas '.$prefix,
            'email' => $prefix.'@keliling.test', 'password' => 'password123',
        ]);
        $s1 = Santri::create(['nama' => 'Santri Satu', 'nis' => 'K1-'.$prefix, 'status' => 'aktif', 'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L']);
        $s2 = Santri::create(['nama' => 'Santri Dua', 'nis' => 'K2-'.$prefix, 'status' => 'aktif', 'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'P']);

        return [app(SetorKelilingService::class), $s1, $s2, $petugas];
    }

    public function test_alur_penuh_draf_tidak_menyentuh_saldo_dan_approve_posting_via_jalur_teruji(): void
    {
        [$svc, $s1, $s2, $petugas] = $this->boot('sk1');
        $tab = app(TabunganService::class);

        $sesi = $svc->buatSesi($petugas->id, 'Asrama Putra');
        $svc->tambahItem($sesi, $s1, 50000);
        $svc->tambahItem($sesi, $s2, 25000, 'titip ibu');

        // Draf & diajukan = NOL efek saldo.
        $this->assertSame(0.0, $tab->getSaldo($s1));
        $sesi = $svc->ajukan($sesi);
        $this->assertSame('diajukan', $sesi->status);
        $this->assertSame(0.0, $tab->getSaldo($s2));
        $this->assertEqualsWithDelta(75000.0, (float) $sesi->total, 0.01);

        // Approve → saldo bertambah LEWAT TabunganService (transaksi tercatat).
        $sesi = $svc->setujui($sesi, $petugas->id);
        $this->assertSame('disetujui', $sesi->status);
        $this->assertSame(50000.0, $tab->getSaldo($s1));
        $this->assertSame(25000.0, $tab->getSaldo($s2));
        $this->assertSame(2, TabunganTransaksi::query()->where('tipe', 'setor')->count());
        $this->assertStringContainsString('Setor keliling #'.$sesi->id, TabunganTransaksi::query()->first()->keterangan);

        // Idempoten: approve ulang DITOLAK, saldo tidak dobel.
        try {
            $svc->setujui($sesi, $petugas->id);
            $this->fail('Approve ganda harus ditolak.');
        } catch (InvalidArgumentException) {
        }
        $this->assertSame(50000.0, $tab->getSaldo($s1));
        $this->assertSame(2, TabunganTransaksi::query()->count());
        tenancy()->end();
    }

    public function test_tolak_tidak_memposting_dan_guard_status(): void
    {
        [$svc, $s1, , $petugas] = $this->boot('sk2');
        $tab = app(TabunganService::class);

        $sesi = $svc->buatSesi($petugas->id);

        // Sesi kosong tak bisa diajukan.
        try {
            $svc->ajukan($sesi);
            $this->fail('Sesi kosong harus ditolak.');
        } catch (InvalidArgumentException) {
        }

        $svc->tambahItem($sesi, $s1, 10000);
        $sesi = $svc->ajukan($sesi);

        // Setelah diajukan, item terkunci.
        try {
            $svc->tambahItem($sesi, $s1, 5000);
            $this->fail('Tambah item pasca-ajukan harus ditolak.');
        } catch (InvalidArgumentException) {
        }

        $sesi = $svc->tolak($sesi, $petugas->id, 'Uang fisik tidak sesuai');
        $this->assertSame('ditolak', $sesi->status);
        $this->assertSame('Uang fisik tidak sesuai', $sesi->alasan_tolak);
        $this->assertSame(0.0, $tab->getSaldo($s1));
        $this->assertSame(0, TabunganTransaksi::query()->count());

        // Sesi ditolak tak bisa disetujui belakangan.
        $this->expectException(InvalidArgumentException::class);
        $svc->setujui($sesi, $petugas->id);
        tenancy()->end();
    }

    public function test_item_tidak_bisa_diselundupkan_lewat_instance_basi(): void
    {
        [$svc, $s1, , $petugas] = $this->boot('sk4');

        $sesiStale = $svc->buatSesi($petugas->id);
        $svc->tambahItem($sesiStale, $s1, 10000);
        $svc->ajukan($sesiStale); // instance $sesiStale TIDAK di-refresh → status in-memory masih 'draf'

        // Guard harus membaca status dari baris ter-lock, bukan instance basi.
        try {
            $svc->tambahItem($sesiStale, $s1, 999999);
            $this->fail('Item lewat instance basi pasca-ajukan harus ditolak.');
        } catch (InvalidArgumentException) {
        }

        $this->assertSame(1, SetorKelilingSesi::query()->firstOrFail()->items()->count());
        $this->assertEqualsWithDelta(10000.0, (float) SetorKelilingSesi::query()->firstOrFail()->total, 0.01);
        tenancy()->end();
    }

    public function test_nominal_nol_atau_negatif_ditolak(): void
    {
        [$svc, $s1, , $petugas] = $this->boot('sk3');
        $sesi = $svc->buatSesi($petugas->id);

        $this->expectException(InvalidArgumentException::class);
        $svc->tambahItem($sesi, $s1, 0);
    }
}
