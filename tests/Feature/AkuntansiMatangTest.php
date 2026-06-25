<?php

namespace Tests\Feature;

use InvalidArgumentException;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\AlokasiBiaya;
use Modules\Ledger\Models\DivisiUnit;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Services\AlokasiBiayaService;
use Modules\Ledger\Services\CoaTemplateService;
use Modules\Ledger\Services\KursService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G3-23 — Cost allocation (Σ share = nominal, jurnal seimbang), multi-currency
 * (IDR tak korup), COA wizard. WP UANG.
 */
class AkuntansiMatangTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'sumber_dana', 'divisi_unit',
            'periode_akuntansi', 'jurnal', 'jurnal_baris', 'alokasi_biaya', 'kurs_valuta', 'audit_log',
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

    private function akunBeban(): Akun
    {
        return Akun::where('kode', '5-1000')->firstOrFail();
    }

    private function divisi(string $kode): DivisiUnit
    {
        return DivisiUnit::where('kode', $kode)->firstOrFail();
    }

    public function test_alokasi_berbobot_jurnal_seimbang_dan_sum_sama_nominal(): void
    {
        $this->boot('akm1');
        $akun = $this->akunBeban();
        $ma = $this->divisi('MA');
        $mts = $this->divisi('MTS');

        // 1jt, bobot MA:2, MTS:3 → MA 400rb, MTS 600rb.
        $alokasi = app(AlokasiBiayaService::class)->alokasikan($akun, 1000000, [
            $ma->id => 2, $mts->id => 3,
        ], ['periode' => '2026-06']);

        $this->assertSame('posted', $alokasi->status);
        $jurnal = Jurnal::with('baris')->find($alokasi->jurnal_id);
        $this->assertEqualsWithDelta($jurnal->baris->sum('debit'), $jurnal->baris->sum('kredit'), 0.01);
        $this->assertEqualsWithDelta(1000000, (float) $jurnal->baris->sum('debit'), 0.01); // Σ share = nominal
        // Per divisi.
        $this->assertEqualsWithDelta(400000, (float) $jurnal->baris->where('divisi_id', $ma->id)->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(600000, (float) $jurnal->baris->where('divisi_id', $mts->id)->sum('debit'), 0.01);
        tenancy()->end();
    }

    public function test_alokasi_pembulatan_residu_tetap_sum_nominal(): void
    {
        $this->boot('akm2');
        $akun = $this->akunBeban();
        $a = $this->divisi('MA');
        $b = $this->divisi('MTS');
        $c = $this->divisi('MAHAD');

        // 1jt / 3 → 333.333,33 × 3 = 999.999,99; residu 0,01 ke bobot terbesar.
        $alokasi = app(AlokasiBiayaService::class)->alokasikan($akun, 1000000, [
            $a->id => 1, $b->id => 1, $c->id => 1,
        ]);
        $jurnal = Jurnal::with('baris')->find($alokasi->jurnal_id);
        $this->assertEqualsWithDelta(1000000, (float) $jurnal->baris->sum('debit'), 0.001); // PERSIS, tak ada uang hilang
        $this->assertEqualsWithDelta($jurnal->baris->sum('debit'), $jurnal->baris->sum('kredit'), 0.001);
        tenancy()->end();
    }

    public function test_alokasi_guard_nominal_dan_bobot(): void
    {
        $this->boot('akm3');
        $akun = $this->akunBeban();
        $svc = app(AlokasiBiayaService::class);
        $this->expectException(InvalidArgumentException::class);
        $svc->alokasikan($akun, 0, [$this->divisi('MA')->id => 1]); // nominal 0
        tenancy()->end();
    }

    public function test_kurs_konversi_dan_donor_idr_seimbang(): void
    {
        $this->boot('akm4');
        $svc = app(KursService::class);
        $svc->setKurs('USD', 16000, '2026-06-01');
        $this->assertEqualsWithDelta(1600000, $svc->konversiKeIdr(100, 'USD', '2026-06-10'), 0.01);

        $kas = Akun::kasBank()->where('is_postable', true)->orderBy('kode')->first();
        $pendapatan = Akun::where('kode', '4-3000')->first(); // Penerimaan Wakaf
        $jurnal = $svc->catatPenerimaanDonor($kas, $pendapatan, 100, 'USD', ['tanggal' => '2026-06-10']);

        // Posting IDR seimbang; metadata valuta tercatat.
        $jurnal->loadMissing('baris');
        $this->assertEqualsWithDelta(1600000, (float) $jurnal->baris->sum('debit'), 0.01);
        $this->assertEqualsWithDelta($jurnal->baris->sum('debit'), $jurnal->baris->sum('kredit'), 0.01);
        $this->assertSame('USD', $jurnal->mata_uang);
        $this->assertEqualsWithDelta(100, (float) $jurnal->nilai_asing, 0.01);
        $this->assertEqualsWithDelta(16000, (float) $jurnal->kurs, 0.01);
        tenancy()->end();
    }

    public function test_donor_idempoten_tak_double_post(): void
    {
        $this->boot('akmD');
        $svc = app(KursService::class);
        $svc->setKurs('SAR', 4200, '2026-06-01');
        $kas = Akun::kasBank()->where('is_postable', true)->orderBy('kode')->first();
        $pendapatan = Akun::where('kode', '4-3000')->first();

        $args = [$kas, $pendapatan, 500, 'SAR', ['tanggal' => '2026-06-10']];
        $j1 = $svc->catatPenerimaanDonor(...$args);
        $j2 = $svc->catatPenerimaanDonor(...$args); // submit ulang identik

        $this->assertSame($j1->id, $j2->id, 'Submit ulang identik tak boleh membuat jurnal baru.');
        $this->assertSame(1, Jurnal::where('tipe_sumber', 'donor_valas')->count(), 'Donor valas tak boleh double-post.');
        // Beda ref_id eksplisit → jurnal terpisah (donasi sah berbeda).
        $j3 = $svc->catatPenerimaanDonor($kas, $pendapatan, 500, 'SAR', ['tanggal' => '2026-06-10', 'ref_id' => 'KW-002']);
        $this->assertNotSame($j1->id, $j3->id);
        $this->assertSame(2, Jurnal::where('tipe_sumber', 'donor_valas')->count());
        tenancy()->end();
    }

    /**
     * Kunci invarian Σshare = nominal PERSIS pada kasus pembulatan sulit:
     * bobot pecahan, banyak divisi, nominal kecil/ganjil. Jurnal wajib seimbang.
     */
    public function test_alokasi_invarian_pembulatan_kasus_sulit(): void
    {
        $this->boot('akmR');
        $akun = $this->akunBeban();
        $svc = app(AlokasiBiayaService::class);
        // Kode divisi yang DIPASTIKAN diseed LedgerSeeder.
        $kodeDivisi = ['MA', 'MTS', 'MAHAD', 'DAPUR', 'SARPRAS', 'USAHA'];
        $divisiIds = [];
        foreach ($kodeDivisi as $k) {
            $d = DivisiUnit::where('kode', $k)->first();
            if ($d) {
                $divisiIds[] = $d->id;
            }
        }
        $this->assertGreaterThanOrEqual(4, count($divisiIds), 'Butuh ≥4 divisi untuk uji pembulatan.');

        $kasus = [
            ['nominal' => 100.00, 'bobot' => [0.334, 0.333, 0.333]],   // pecahan
            ['nominal' => 0.07, 'bobot' => array_fill(0, count($divisiIds), 1)], // nominal kecil, banyak divisi
            ['nominal' => 1000000.01, 'bobot' => [1, 1, 1]],            // ganjil 1 sen
            ['nominal' => 33333.33, 'bobot' => [2, 1, 1, 1]],           // campuran
        ];
        foreach ($kasus as $i => $c) {
            $driver = [];
            foreach ($c['bobot'] as $j => $b) {
                $driver[$divisiIds[$j]] = $b;
            }
            $alokasi = $svc->alokasikan($akun, $c['nominal'], $driver);
            $jurnal = Jurnal::with('baris')->find($alokasi->jurnal_id);
            $debit = (float) $jurnal->baris->sum('debit');
            $kredit = (float) $jurnal->baris->sum('kredit');
            $this->assertEqualsWithDelta(round($c['nominal'], 2), $debit, 0.001, "Kasus {$i}: Σshare harus = nominal PERSIS.");
            $this->assertEqualsWithDelta($debit, $kredit, 0.001, "Kasus {$i}: jurnal harus seimbang.");
        }
        tenancy()->end();
    }

    public function test_kurs_belum_diset_ditolak(): void
    {
        $this->boot('akm5');
        $this->expectException(InvalidArgumentException::class);
        app(KursService::class)->konversiKeIdr(100, 'EUR');
        tenancy()->end();
    }

    public function test_coa_wizard_idempoten(): void
    {
        $this->boot('akm6');
        $svc = app(CoaTemplateService::class);
        $n1 = $svc->terapkanStandar();
        $n2 = $svc->terapkanStandar(); // idempoten — tak menggandakan
        $this->assertSame($n1, $n2);
        $this->assertGreaterThan(0, $n1);
        tenancy()->end();
    }

    public function test_isolasi_tenant_alokasi(): void
    {
        $this->boot('akmA');
        app(AlokasiBiayaService::class)->alokasikan($this->akunBeban(), 100000, [$this->divisi('MA')->id => 1]);
        $this->assertSame(1, AlokasiBiaya::query()->count());
        tenancy()->end();

        $this->boot('akmB');
        $this->assertSame(0, AlokasiBiaya::query()->count(), 'Alokasi tenant lain tak boleh terlihat.');
        tenancy()->end();
    }
}
