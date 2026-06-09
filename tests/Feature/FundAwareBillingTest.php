<?php

namespace Tests\Feature;

use Modules\Keuangan\Models\SkemaBiaya;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\FundAwareBillingService;
use Modules\Keuangan\Services\SkemaBiayaService;
use Modules\Keuangan\Services\TagihanService;
use Modules\Kesantrian\Models\Santri;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\SumberDana;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G2-15 — Fund-aware Billing. Dana negara (BOS/BOP) tak ditagihkan ke wali;
 * Tagihan menyimpan sumber dana + flag dibayar-di-muka; ringkasan piutang per
 * klasifikasi kehalalan.
 */
class FundAwareBillingTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'sumber_dana', 'divisi_unit',
            'santri', 'kelas', 'tagihan', 'periode_akuntansi', 'akun', 'skema_biaya',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
    }

    private function sumberId(string $kode): int
    {
        return (int) SumberDana::where('kode_dana', $kode)->value('id');
    }

    private function santri(): Santri
    {
        return Santri::create([
            'nis' => 'FA-'.str()->random(5), 'nama' => 'Santri', 'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
    }

    public function test_boleh_dibebankan_ke_wali(): void
    {
        $this->boot('fa1');
        $svc = app(FundAwareBillingService::class);
        $this->assertFalse($svc->bolehDibebankanKeWali($this->sumberId('01'))); // BOS = negara
        $this->assertFalse($svc->bolehDibebankanKeWali($this->sumberId('02'))); // BOP = negara
        $this->assertTrue($svc->bolehDibebankanKeWali($this->sumberId('03')));  // SPP/swadaya = komersial
        $this->assertTrue($svc->bolehDibebankanKeWali(null));
        tenancy()->end();
    }

    public function test_generate_skema_bos_di_skip(): void
    {
        $this->boot('fa2');
        SkemaBiaya::create(['tahun_ajaran' => '2026/2027', 'jenis_tagihan' => 'spp',
            'nominal' => 500000, 'sumber_dana_id' => $this->sumberId('01'), 'aktif' => true]); // BOS
        $hasil = app(SkemaBiayaService::class)->generateTagihan($this->santri(), '2026/2027', 'spp', [
            'jatuh_tempo' => '2026-07-10', 'bulan_masehi' => '2026-07',
        ]);
        $this->assertNull($hasil); // ditanggung BOS → tak ada tagihan wali
        $this->assertSame(0, Tagihan::query()->count());
        tenancy()->end();
    }

    public function test_tagihan_menyimpan_sumber_dana_dan_flag_dimuka(): void
    {
        $this->boot('fa3');
        $tagihan = app(TagihanService::class)->buatTagihanUntukSantri($this->santri(), [
            'jenis_tagihan' => 'spp', 'nominal' => 500000, 'jatuh_tempo' => '2026-07-10', 'bulan_masehi' => '2026-07',
            'sumber_dana_id' => $this->sumberId('03'), 'is_dibayar_dimuka' => true,
        ]);
        $this->assertSame($this->sumberId('03'), (int) $tagihan->sumber_dana_id);
        $this->assertTrue($tagihan->fresh()->is_dibayar_dimuka);
        tenancy()->end();
    }

    public function test_ringkasan_piutang_per_kehalalan(): void
    {
        $this->boot('fa4');
        $svc = app(TagihanService::class);
        $svc->buatTagihanUntukSantri($this->santri(), ['jenis_tagihan' => 'spp', 'nominal' => 500000,
            'jatuh_tempo' => '2026-07-10', 'bulan_masehi' => '2026-07', 'sumber_dana_id' => $this->sumberId('03')]); // halal
        $svc->buatTagihanUntukSantri($this->santri(), ['jenis_tagihan' => 'lainnya', 'nominal' => 200000,
            'jatuh_tempo' => '2026-07-10', 'bulan_masehi' => '2026-07', 'sumber_dana_id' => $this->sumberId('90')]); // syubhat

        $r = app(FundAwareBillingService::class)->ringkasanPiutangPerKehalalan();
        $this->assertEqualsWithDelta(500000, $r['halal'], 0.01);
        $this->assertEqualsWithDelta(200000, $r['syubhat'], 0.01);
        $this->assertEqualsWithDelta(700000, $r['total'], 0.01);
        tenancy()->end();
    }
}
