<?php

namespace Tests\Feature;

use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\SkemaBiaya;
use Modules\Keuangan\Services\SkemaBiayaService;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\DivisiUnit;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G2-16 — Pricing Rule (SkemaBiaya). Menguji resolusi tarif (spesifik > umum),
 * nominalUntuk, generate Tagihan via TagihanService (+ diskon), dan isolasi.
 */
class SkemaBiayaTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'skema_biaya', 'divisi_unit', 'sumber_dana',
            'santri', 'kelas', 'tagihan', 'periode_akuntansi', 'akun',
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

    private function divisi(string $kode): DivisiUnit
    {
        return DivisiUnit::create([
            'kode' => $kode, 'nama_divisi' => 'Divisi '.$kode, 'tipe_layanan' => 'pendidikan',
            'is_komersial' => false, 'is_active' => true,
        ]);
    }

    private function skema(string $tahun, ?int $divisiId, string $jenis, float $nominal): SkemaBiaya
    {
        return SkemaBiaya::create([
            'tahun_ajaran' => $tahun, 'divisi_id' => $divisiId, 'jenis_tagihan' => $jenis,
            'nominal' => $nominal, 'aktif' => true,
        ]);
    }

    private function santri(): Santri
    {
        return Santri::create([
            'nis' => 'SK-'.str()->random(5), 'nama' => 'Santri', 'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
    }

    public function test_resolve_spesifik_diutamakan_lalu_umum(): void
    {
        $this->boot('skb1');
        $a = $this->divisi('SMP');
        $this->skema('2026/2027', null, 'spp', 100000);       // umum
        $this->skema('2026/2027', $a->id, 'spp', 150000);     // spesifik SMP

        $svc = app(SkemaBiayaService::class);
        $this->assertEqualsWithDelta(150000, $svc->nominalUntuk('2026/2027', $a->id, 'spp'), 0.01);
        $this->assertEqualsWithDelta(100000, $svc->nominalUntuk('2026/2027', 99999, 'spp'), 0.01); // divisi lain → umum
        $this->assertEqualsWithDelta(100000, $svc->nominalUntuk('2026/2027', null, 'spp'), 0.01);
        $this->assertEqualsWithDelta(0, $svc->nominalUntuk('2026/2027', null, 'seragam'), 0.01); // tak ada
        tenancy()->end();
    }

    public function test_generate_tagihan_dengan_diskon(): void
    {
        $this->boot('skb2');
        $this->skema('2026/2027', null, 'spp', 500000);
        $santri = $this->santri();

        $tagihan = app(SkemaBiayaService::class)->generateTagihan($santri, '2026/2027', 'spp', [
            'diskon' => 50000, 'jatuh_tempo' => '2026-07-10', 'bulan_masehi' => '2026-07',
        ]);

        $this->assertNotNull($tagihan);
        $this->assertEqualsWithDelta(500000, (float) $tagihan->nominal, 0.01);
        $this->assertEqualsWithDelta(50000, (float) $tagihan->diskon, 0.01);
        $this->assertEqualsWithDelta(450000, (float) $tagihan->nominal_akhir, 0.01);
        tenancy()->end();
    }

    public function test_generate_null_tanpa_skema(): void
    {
        $this->boot('skb3');
        $tagihan = app(SkemaBiayaService::class)->generateTagihan($this->santri(), '2099/2100', 'spp', [
            'jatuh_tempo' => '2026-07-10', 'bulan_masehi' => '2026-07',
        ]);
        $this->assertNull($tagihan);
        tenancy()->end();
    }

    public function test_isolasi_tenant(): void
    {
        $this->boot('skbA');
        $this->skema('2026/2027', null, 'spp', 500000);
        $this->assertSame(1, SkemaBiaya::query()->count());
        tenancy()->end();

        $this->boot('skbB');
        $this->assertSame(0, SkemaBiaya::query()->count());
        tenancy()->end();
    }
}
