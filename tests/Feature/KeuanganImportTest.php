<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\KeuanganImportService;
use Modules\Keuangan\Services\TagihanService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Import tagihan historis + pelunasan (UANG): semua lewat TagihanService/
 * PembayaranService, idempoten saat dijalankan ulang, dry-run bersih,
 * guard nominal/diskon/NIS/verifikator.
 */
class KeuanganImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    private User $verifikator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('keu', 'Pesantren Keuangan');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        $this->verifikator = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bendahara',
            'email' => 'bendahara@uji.test',
            'password' => bcrypt('rahasia-uji'),
        ]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function santri(string $nis): Santri
    {
        return Santri::create([
            'tenant_id' => $this->tenant->id,
            'nis' => $nis,
            'nama' => 'Santri '.$nis,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status' => 'aktif',
        ]);
    }

    /** @param list<string> $baris */
    private function csv(array $baris): string
    {
        $path = tempnam(sys_get_temp_dir(), 'keu-import-').'.csv';
        file_put_contents($path, implode("\n", $baris)."\n");

        return $path;
    }

    public function test_import_tagihan_dan_pelunasan_via_service_resmi(): void
    {
        $this->santri('1001');
        $this->santri('1002');

        $file = $this->csv([
            'nis,jenis_tagihan,nominal,diskon,jatuh_tempo,bulan,status_bayar,tanggal_pembayaran,metode',
            '1001,SPP,500000,0,2026-05-10,2026-05,lunas,2026-05-08,tunai',
            '1002,SPP,500000,100000,2026-05-10,2026-05,belum,,',
        ]);

        $summary = app(KeuanganImportService::class)
            ->import($this->tenant, $file, $this->verifikator->id);

        $this->assertSame(2, $summary['tagihan_dibuat']);
        $this->assertSame(1, $summary['pembayaran_diverifikasi']);
        $this->assertSame(0, $summary['failed']);
        $this->assertSame(900000.0, $summary['total_tagihan']); // 500rb + (500rb-100rb)
        $this->assertSame(500000.0, $summary['total_terbayar']);

        $lunas = Tagihan::query()->whereHas('santri', fn ($q) => $q->where('nis', '1001'))->first();
        $this->assertSame('lunas', $lunas->status);

        $bayar = Pembayaran::query()->where('tagihan_id', $lunas->id)->first();
        $this->assertTrue((bool) $bayar->is_verified);
        $this->assertSame($this->verifikator->id, (int) $bayar->verified_by);
        $this->assertSame('2026-05-08', $bayar->tanggal_pembayaran->toDateString());

        $belum = Tagihan::query()->whereHas('santri', fn ($q) => $q->where('nis', '1002'))->first();
        $this->assertSame('belum_bayar', $belum->status);
        $this->assertSame(400000.0, (float) $belum->nominal_akhir);
    }

    public function test_idempoten_jalankan_ulang_semua_dilewati(): void
    {
        $this->santri('1001');

        $file = $this->csv([
            'nis,jenis_tagihan,nominal,jatuh_tempo,bulan,status_bayar',
            '1001,SPP,500000,2026-05-10,2026-05,lunas',
        ]);

        $svc = app(KeuanganImportService::class);
        $svc->import($this->tenant, $file, $this->verifikator->id);
        $ulang = $svc->import($this->tenant, $file, $this->verifikator->id);

        $this->assertSame(0, $ulang['tagihan_dibuat']);
        $this->assertSame(1, $ulang['skipped']);
        $this->assertSame(1, Tagihan::query()->count());
        $this->assertSame(1, Pembayaran::query()->count()); // TIDAK dobel bayar
    }

    public function test_guard_uang_nominal_diskon_nis_verifikator(): void
    {
        $this->santri('1001');

        $file = $this->csv([
            'nis,jenis_tagihan,nominal,diskon,jatuh_tempo,status_bayar',
            '1001,SPP,0,0,2026-05-10,belum',        // nominal 0
            '1001,SPP,500000,600000,2026-05-10,belum', // diskon > nominal
            '9999,SPP,500000,0,2026-05-10,belum',   // NIS tak dikenal
            '1001,Makan,500000,0,2026-05-10,lunas', // lunas tanpa verifikator
        ]);

        $summary = app(KeuanganImportService::class)
            ->import($this->tenant, $file, verifikatorId: null);

        $this->assertSame(4, $summary['failed']);
        $this->assertSame(0, $summary['tagihan_dibuat']);
        $this->assertSame(0, Tagihan::query()->count());
        $this->assertSame(0, Pembayaran::query()->count());
    }

    public function test_self_heal_tagihan_yatim_dilengkapi_pelunasannya(): void
    {
        // Simulasi run sebelumnya gagal di tengah: tagihan sudah ada
        // berstatus belum_bayar, pelunasan historisnya belum masuk.
        $santri = $this->santri('1001');
        app(TagihanService::class)->buatTagihanUntukSantri($santri, [
            'jenis_tagihan' => 'SPP',
            'nominal' => 500000,
            'jatuh_tempo' => '2026-05-10',
            'bulan_masehi' => '2026-05',
        ]);

        $file = $this->csv([
            'nis,jenis_tagihan,nominal,jatuh_tempo,bulan,status_bayar',
            '1001,SPP,500000,2026-05-10,2026-05,lunas',
        ]);

        $summary = app(KeuanganImportService::class)
            ->import($this->tenant, $file, $this->verifikator->id);

        // Tidak membuat tagihan baru, tapi pelunasan DILENGKAPI.
        $this->assertSame(0, $summary['tagihan_dibuat']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(1, $summary['pembayaran_diverifikasi']);
        $this->assertSame(1, Tagihan::query()->count());
        $this->assertSame('lunas', Tagihan::query()->first()->status);
        $this->assertSame(1, Pembayaran::query()->where('is_verified', true)->count());

        // Run ketiga: benar-benar idempoten, tidak dobel bayar.
        $ulang = app(KeuanganImportService::class)->import($this->tenant, $file, $this->verifikator->id);
        $this->assertSame(0, $ulang['pembayaran_diverifikasi']);
        $this->assertSame(1, Pembayaran::query()->count());
    }

    public function test_dry_run_tidak_menulis_uang_apapun(): void
    {
        $this->santri('1001');

        $file = $this->csv([
            'nis,jenis_tagihan,nominal,jatuh_tempo,bulan,status_bayar',
            '1001,SPP,500000,2026-05-10,2026-05,lunas',
        ]);

        $summary = app(KeuanganImportService::class)
            ->import($this->tenant, $file, $this->verifikator->id, dryRun: true);

        $this->assertSame(1, $summary['tagihan_dibuat']);
        $this->assertSame(1, $summary['pembayaran_diverifikasi']);
        $this->assertSame(500000.0, $summary['total_terbayar']);
        $this->assertTrue($summary['dry_run']);

        $this->assertSame(0, Tagihan::query()->count());
        $this->assertSame(0, Pembayaran::query()->count());
    }
}
