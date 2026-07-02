<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\SyahriyyahImportService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Import syahriyyah format asli Asy-Syifaa (multi-baris cicilan, kolom
 * CASH/BSI/BCA): tagihan auto per bulan, cicilan menumpuk sampai lunas,
 * idempoten re-run, kelebihan bayar ditolak service.
 */
class SyahriyyahImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    private User $verifikator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('sya', 'Pesantren Syahriyyah');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        $this->verifikator = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bendahara',
            'email' => 'bendahara@uji.test',
            'password' => bcrypt('rahasia-uji'),
        ]);

        Santri::create([
            'tenant_id' => $this->tenant->id,
            'nis' => '22230001',
            'nama' => 'Ahmad',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status' => 'aktif',
        ]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    /** @param list<list<mixed>> $rows format asli: No|NIS|NAMA|P/L|PEMBAYARAN|CASH|BSI|BCA|LAINNYA|TANGGAL|CATATAN */
    private function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sya-import-').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['No', 'NIS', 'NAMA SANTRI', 'P/L', 'PEMBAYARAN', 'CASH', 'BSI', 'BCA', 'LAINNYA', 'TANGGAL', 'CATATAN']));
        foreach ($rows as $r) {
            $writer->addRow(Row::fromValues($r));
        }
        $writer->close();

        return $path;
    }

    public function test_cicilan_menumpuk_sampai_lunas_dan_metode_terbaca(): void
    {
        $file = $this->xlsx([
            [1, '22230001', 'Ahmad', 'Banin', 'Cicilan Agustus 2025', 250000, 0, 0, 0, '2025-08-05', ''],
            [2, '22230001', 'Ahmad', 'Banin', 'Pelunasan Agustus 2025', 0, 450000, 0, 0, '2025-08-20', ''],
        ]);

        $summary = app(SyahriyyahImportService::class)
            ->import($this->tenant, $file, $this->verifikator->id);

        $this->assertSame(2, $summary['pembayaran_masuk']);
        $this->assertSame(1, $summary['tagihan_dibuat']);
        $this->assertSame(0, $summary['failed']);
        $this->assertSame(700000.0, $summary['total_terbayar']);

        $tagihan = Tagihan::query()->where('bulan_masehi', '2025-08')->first();
        $this->assertNotNull($tagihan);
        $this->assertSame('lunas', $tagihan->status); // 250rb + 450rb = 700rb
        $this->assertSame(700000.0, (float) $tagihan->nominal_akhir);

        $bayar = Pembayaran::query()->orderBy('tanggal_pembayaran')->get();
        $this->assertSame(['tunai', 'transfer'], $bayar->pluck('metode')->all());
        $this->assertTrue($bayar->every(fn ($p) => (bool) $p->is_verified));
    }

    public function test_idempoten_re_run_tidak_dobel_pembayaran(): void
    {
        $file = $this->xlsx([
            [1, '22230001', 'Ahmad', 'Banin', 'Agustus 2025', 700000, 0, 0, 0, '2025-08-05', ''],
        ]);

        $svc = app(SyahriyyahImportService::class);
        $svc->import($this->tenant, $file, $this->verifikator->id);
        $ulang = $svc->import($this->tenant, $file, $this->verifikator->id);

        $this->assertSame(0, $ulang['pembayaran_masuk']);
        $this->assertSame(1, $ulang['skipped']);
        $this->assertSame(1, Pembayaran::query()->count());
        $this->assertSame(1, Tagihan::query()->count());
    }

    public function test_kelebihan_bayar_ditolak_service_dan_nis_asing_gagal(): void
    {
        $file = $this->xlsx([
            [1, '22230001', 'Ahmad', 'Banin', 'Agustus 2025', 700000, 0, 0, 0, '2025-08-05', ''],
            // Pembayaran kedua bulan sama nominal beda → melebihi sisa (0) → service tolak.
            [2, '22230001', 'Ahmad', 'Banin', 'Agustus 2025', 100000, 0, 0, 0, '2025-08-25', ''],
            [3, '99990001', 'Asing', 'Banin', 'Agustus 2025', 700000, 0, 0, 0, '2025-08-05', ''],
        ]);

        $summary = app(SyahriyyahImportService::class)
            ->import($this->tenant, $file, $this->verifikator->id);

        $this->assertSame(1, $summary['pembayaran_masuk']);
        $this->assertSame(2, $summary['failed']);
        $this->assertSame(700000.0, (float) Pembayaran::query()->sum('nominal'));
    }

    public function test_dry_run_dan_parse_only_tidak_menulis(): void
    {
        $file = $this->xlsx([
            [1, '22230001', 'Ahmad', 'Banin', 'Agustus 2025', 700000, 0, 0, 0, '2025-08-05', ''],
        ]);

        $svc = app(SyahriyyahImportService::class);

        $dry = $svc->import($this->tenant, $file, $this->verifikator->id, dryRun: true);
        $this->assertSame(1, $dry['pembayaran_masuk']);

        $parse = $svc->import(null, $file, null, parseOnly: true);
        $this->assertSame(1, $parse['pembayaran_masuk']);
        $this->assertSame(700000.0, $parse['total_terbayar']);

        $this->assertSame(0, Tagihan::query()->count());
        $this->assertSame(0, Pembayaran::query()->count());
    }
}
