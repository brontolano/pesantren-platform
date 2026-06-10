<?php

namespace Tests\Feature;

use InvalidArgumentException;
use Modules\Keuangan\Models\SkemaBiaya;
use Modules\Keuangan\Models\Tagihan;
use Modules\Kesantrian\Models\Santri;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\CrmAktivitas;
use Modules\PPDB\Services\CrmService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G3-20 — CRM ringan: funnel, churn (tunggakan), quotation dari SkemaBiaya,
 * campaign re-enrollment idempoten. Non-uang (tak posting jurnal).
 */
class CrmTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'calon_santri',
            'tagihan', 'pembayaran', 'skema_biaya', 'crm_penawaran', 'crm_aktivitas', 'audit_log',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    private function svc(): CrmService
    {
        return app(CrmService::class);
    }

    private function calon(string $status, string $tahun = '2026', array $over = []): CalonSantri
    {
        return CalonSantri::create(array_merge([
            'nomor_pendaftaran' => 'REG'.substr(md5(uniqid()), 0, 6),
            'nama' => 'Calon '.$status, 'jenis_kelamin' => 'L', 'tanggal_lahir' => '2012-01-01',
            'status_pendaftaran' => $status, 'tahun_ajaran' => $tahun,
        ], $over));
    }

    private function santri(string $nis): Santri
    {
        return Santri::create([
            'nis' => 'CRM'.$nis.substr(md5(uniqid()), 0, 3), 'nama' => 'Santri '.$nis,
            'tanggal_lahir' => '2011-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
    }

    private function tagihan(Santri $s, float $nominal, string $status = 'belum_bayar'): Tagihan
    {
        return Tagihan::create([
            'santri_id' => $s->id, 'jenis_tagihan' => 'spp', 'nominal' => $nominal,
            'nominal_akhir' => $nominal, 'status' => $status, 'periode_akademik' => '2026',
            'jatuh_tempo' => '2026-07-01',
        ]);
    }

    public function test_funnel_hitung_per_status(): void
    {
        $this->boot('crm1');
        $this->calon('pending');
        $this->calon('pending');
        $this->calon('lulus');
        $this->calon('daftar_ulang');

        $funnel = $this->svc()->funnel('2026');
        $this->assertSame(2, $funnel['pending']);
        $this->assertSame(1, $funnel['lulus']);
        $this->assertSame(1, $funnel['daftar_ulang']);
        tenancy()->end();
    }

    public function test_churn_berisiko_dari_tunggakan(): void
    {
        $this->boot('crm2');
        $a = $this->santri('A');
        $b = $this->santri('B');
        $c = $this->santri('C');
        $this->tagihan($a, 500000);                 // nunggak 500rb
        $this->tagihan($a, 300000);                 // + 300rb = 800rb
        $this->tagihan($b, 200000);                 // nunggak 200rb
        $this->tagihan($c, 100000, 'lunas');        // lunas → tak berisiko

        $churn = $this->svc()->churnBerisiko(['ambang_tunggakan' => 0]);
        $this->assertCount(2, $churn);
        $this->assertSame($a->id, $churn[0]['santri_id']);          // terbesar dulu
        $this->assertEqualsWithDelta(800000, $churn[0]['tunggakan'], 0.01);
        $this->assertEqualsWithDelta(200000, $churn[1]['tunggakan'], 0.01);
        tenancy()->end();
    }

    public function test_buat_penawaran_dari_skema_biaya(): void
    {
        $this->boot('crm3');
        SkemaBiaya::create(['tahun_ajaran' => '2026', 'jenis_tagihan' => 'pendaftaran', 'nominal' => 250000, 'aktif' => true]);
        SkemaBiaya::create(['tahun_ajaran' => '2026', 'jenis_tagihan' => 'spp', 'nominal' => 400000, 'aktif' => true]);

        $p = $this->svc()->buatPenawaran([
            'nama_prospek' => 'Ahmad', 'tahun_ajaran' => '2026',
            'jenis' => ['pendaftaran', 'spp', 'seragam'], // 'seragam' tak ada skema → dilewati
        ]);
        $this->assertCount(2, $p->items);
        $this->assertEqualsWithDelta(650000, (float) $p->total, 0.01); // 250rb + 400rb
        tenancy()->end();
    }

    public function test_buat_penawaran_tanpa_jenis_ditolak(): void
    {
        $this->boot('crm4');
        $this->expectException(InvalidArgumentException::class);
        $this->svc()->buatPenawaran(['tahun_ajaran' => '2026', 'jenis' => []]);
        tenancy()->end();
    }

    public function test_campaign_daftar_ulang_idempoten(): void
    {
        $this->boot('crm5');
        $this->calon('lulus', '2026');
        $this->calon('lulus', '2026');
        $this->calon('lulus', '2026', ['tanggal_registrasi_ulang' => now()]); // sudah daftar ulang → skip
        $this->calon('pending', '2026'); // belum lulus → skip

        $n1 = $this->svc()->kirimCampaignDaftarUlang('2026');
        $this->assertSame(2, $n1);
        $this->assertSame(2, CrmAktivitas::where('tipe', 'campaign')->count());

        // Jalankan ULANG → idempoten (ref unik per calon).
        $n2 = $this->svc()->kirimCampaignDaftarUlang('2026');
        $this->assertSame(0, $n2);
        $this->assertSame(2, CrmAktivitas::where('tipe', 'campaign')->count());
        tenancy()->end();
    }

    public function test_isolasi_tenant_crm(): void
    {
        $this->boot('crmA');
        $this->calon('pending');
        $this->assertSame(1, CalonSantri::query()->count());
        tenancy()->end();

        $this->boot('crmB');
        $this->assertSame(0, CalonSantri::query()->count(), 'Calon tenant lain tak boleh terlihat.');
        tenancy()->end();
    }
}
