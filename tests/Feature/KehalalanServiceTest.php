<?php

namespace Tests\Feature;

use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Models\JurnalBaris;
use Modules\Ledger\Models\SumberDana;
use Modules\Ledger\Services\KehalalanService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F3 — KehalalanService (classifier + ringkasan pendapatan per klasifikasi + %halal).
 */
class KehalalanServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'jurnal', 'jurnal_baris', 'sumber_dana',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        (new LedgerSeeder)->run();
        $this->seedTenantRow($tenant);
    }

    private function akun(string $kode): int
    {
        return (int) Akun::query()->where('kode', $kode)->value('id');
    }

    private function syubhatSumber(): SumberDana
    {
        return SumberDana::create([
            'kode_dana' => 'SYBH_TST', 'nama_sumber' => 'Jasa Giro (uji)', 'kategori' => 'lainnya',
            'kehalalan' => 'syubhat', 'is_dana_kebajikan' => true, 'is_wakaf' => false,
            'is_terikat' => false, 'is_active' => true,
        ]);
    }

    private function postPendapatan(?int $sumberId, float $nominal): void
    {
        $j = Jurnal::create([
            'nomor' => 'TST-'.str()->random(6), 'tanggal' => now(), 'deskripsi' => 'uji kehalalan',
            'tipe_sumber' => 'manual', 'status' => 'posted',
        ]);
        JurnalBaris::create(['jurnal_id' => $j->id, 'akun_id' => $this->akun('1-1000'), 'debit' => $nominal, 'kredit' => 0]);
        JurnalBaris::create(['jurnal_id' => $j->id, 'akun_id' => $this->akun('4-1000'), 'sumber_dana_id' => $sumberId, 'debit' => 0, 'kredit' => $nominal]);
    }

    public function test_klasifikasi_dan_bukan_halal(): void
    {
        $this->boot('kh1');
        $halal = SumberDana::query()->where('kehalalan', 'halal')->first();
        $syubhat = $this->syubhatSumber();

        $svc = app(KehalalanService::class);
        $this->assertSame('halal', $svc->klasifikasi($halal?->id));
        $this->assertSame('halal', $svc->klasifikasi(null));
        $this->assertSame('syubhat', $svc->klasifikasi($syubhat->id));
        $this->assertFalse($svc->bukanHalal($halal?->id));
        $this->assertTrue($svc->bukanHalal($syubhat->id));
        tenancy()->end();
    }

    public function test_ringkasan_halal_saja_bersih(): void
    {
        $this->boot('kh2');
        $halal = SumberDana::query()->where('kehalalan', 'halal')->first();
        $this->postPendapatan($halal?->id, 500000);

        $r = app(KehalalanService::class)->ringkasanPendapatan();
        $this->assertEqualsWithDelta(500000, $r['halal'], 0.01);
        $this->assertEqualsWithDelta(0, $r['syubhat'], 0.01);
        $this->assertEqualsWithDelta(500000, $r['total'], 0.01);
        $this->assertEqualsWithDelta(100, app(KehalalanService::class)->persenHalal(), 0.01);
        $this->assertSame('bersih', app(KehalalanService::class)->laporan()['status']);
        tenancy()->end();
    }

    public function test_ringkasan_dengan_syubhat_menurunkan_persen(): void
    {
        $this->boot('kh3');
        $halal = SumberDana::query()->where('kehalalan', 'halal')->first();
        $syubhat = $this->syubhatSumber();
        $this->postPendapatan($halal?->id, 300000);
        $this->postPendapatan($syubhat->id, 100000);

        $svc = app(KehalalanService::class);
        $r = $svc->ringkasanPendapatan();
        $this->assertEqualsWithDelta(300000, $r['halal'], 0.01);
        $this->assertEqualsWithDelta(100000, $r['syubhat'], 0.01);
        $this->assertEqualsWithDelta(400000, $r['total'], 0.01);
        $this->assertEqualsWithDelta(75, $svc->persenHalal(), 0.01); // 300k/400k
        $this->assertSame('ada_syubhat', $svc->laporan()['status']);
        tenancy()->end();
    }

    public function test_isolasi_tenant(): void
    {
        $this->boot('khA');
        $halal = SumberDana::query()->where('kehalalan', 'halal')->first();
        $this->postPendapatan($halal?->id, 200000);
        $this->assertEqualsWithDelta(200000, app(KehalalanService::class)->ringkasanPendapatan()['total'], 0.01);
        tenancy()->end();

        $this->boot('khB');
        $this->assertEqualsWithDelta(0, app(KehalalanService::class)->ringkasanPendapatan()['total'], 0.01);
        tenancy()->end();
    }
}
