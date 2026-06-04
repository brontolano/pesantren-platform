<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesehatan\Models\RiwayatKesehatan;
use Modules\Kesehatan\Models\SesiKonseling;
use Modules\Kesehatan\Services\KesehatanService;
use Tests\TestCase;

class KesehatanServiceTest extends TestCase
{
    use RefreshDatabase;

    private KesehatanService $service;
    private Tenant $tenantA;
    private Tenant $tenantB;
    private Santri $santriA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KesehatanService();

        $this->tenantA = Tenant::create(['id' => 'pesantren-a', 'name' => 'Pesantren A', 'is_active' => true]);
        $this->tenantB = Tenant::create(['id' => 'pesantren-b', 'name' => 'Pesantren B', 'is_active' => true]);

        tenancy()->initialize($this->tenantA);
        $this->santriA = Santri::create([
            'tenant_id'     => 'pesantren-a',
            'nis'           => '2024001',
            'nama'          => 'Ahmad Fulan',
            'jenis_kelamin' => 'L',
            'status'        => 'aktif',
        ]);
        tenancy()->end();
    }

    public function test_catat_riwayat_kesehatan(): void
    {
        tenancy()->initialize($this->tenantA);

        $riwayat = $this->service->catatKesehatan($this->santriA, [
            'tanggal' => today()->toDateString(),
            'keluhan' => 'Demam',
            'diagnosa'=> 'ISPA ringan',
            'kondisi' => 'sakit',
            'petugas' => 'Bidan Siti',
        ]);

        $this->assertInstanceOf(RiwayatKesehatan::class, $riwayat);
        $this->assertEquals('sakit', $riwayat->kondisi);
        $this->assertEquals('pesantren-a', $riwayat->tenant_id);

        tenancy()->end();
    }

    public function test_jadwalkan_dan_selesaikan_konseling(): void
    {
        tenancy()->initialize($this->tenantA);

        $sesi = $this->service->jadwalkanKonseling($this->santriA, [
            'tanggal' => today()->toDateString(),
            'topik'   => 'Masalah adaptasi',
        ]);

        $this->assertEquals('terjadwal', $sesi->status);

        $selesai = $this->service->selesaikanKonseling($sesi, [
            'rekomendasi'      => 'Pantau selama 1 minggu',
            'catatan_lanjutan' => 'Santri sudah lebih tenang',
        ]);

        $this->assertEquals('selesai', $selesai->status);
        $this->assertEquals('Pantau selama 1 minggu', $selesai->rekomendasi);

        tenancy()->end();
    }

    public function test_riwayat_tenant_a_tidak_bocor_ke_tenant_b(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->service->catatKesehatan($this->santriA, ['tanggal' => today()->toDateString(), 'kondisi' => 'sakit']);
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $this->assertCount(0, RiwayatKesehatan::all());
        tenancy()->end();
    }

    public function test_riwayat_santri_terurut_terbaru(): void
    {
        tenancy()->initialize($this->tenantA);

        $this->service->catatKesehatan($this->santriA, ['tanggal' => today()->subDays(2)->toDateString(), 'kondisi' => 'baik']);
        $this->service->catatKesehatan($this->santriA, ['tanggal' => today()->toDateString(), 'kondisi' => 'sakit']);

        $riwayat = $this->service->getRiwayatSantri($this->santriA);

        $this->assertCount(2, $riwayat);
        $this->assertEquals(today()->toDateString(), $riwayat->first()->tanggal->toDateString());

        tenancy()->end();
    }
}
