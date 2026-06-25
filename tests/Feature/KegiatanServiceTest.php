<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kegiatan\Models\Kegiatan;
use Modules\Kegiatan\Models\KunjunganWali;
use Modules\Kegiatan\Services\KegiatanService;
use Modules\Kesantrian\Models\Santri;
use Tests\TestCase;

class KegiatanServiceTest extends TestCase
{
    use RefreshDatabase;

    private KegiatanService $service;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Santri $santriA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KegiatanService;

        $this->tenantA = Tenant::create(['id' => 'pesantren-a', 'name' => 'Pesantren A', 'is_active' => true]);
        $this->tenantB = Tenant::create(['id' => 'pesantren-b', 'name' => 'Pesantren B', 'is_active' => true]);

        tenancy()->initialize($this->tenantA);
        $this->santriA = Santri::create([
            'tenant_id' => 'pesantren-a',
            'nis' => '2024001',
            'nama' => 'Ahmad Fulan',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);
        tenancy()->end();
    }

    public function test_buat_kegiatan(): void
    {
        tenancy()->initialize($this->tenantA);

        $kegiatan = $this->service->buatKegiatan('pesantren-a', [
            'nama' => 'Shalat Berjama\'ah Subuh',
            'jenis' => 'wajib',
            'mulai' => now()->toDateTimeString(),
        ]);

        $this->assertInstanceOf(Kegiatan::class, $kegiatan);
        $this->assertEquals('terjadwal', $kegiatan->status);
        $this->assertEquals('pesantren-a', $kegiatan->tenant_id);

        tenancy()->end();
    }

    public function test_catat_presensi_tidak_duplikat(): void
    {
        tenancy()->initialize($this->tenantA);

        $kegiatan = $this->service->buatKegiatan('pesantren-a', [
            'nama' => 'Pengajian Rutin',
            'jenis' => 'wajib',
            'mulai' => now()->toDateTimeString(),
        ]);

        $this->service->catatPresensi($kegiatan, $this->santriA, 'hadir');
        $this->service->catatPresensi($kegiatan, $this->santriA, 'izin', 'Sakit');

        $presensi = $this->service->getPresensiKegiatan($kegiatan);
        $this->assertCount(1, $presensi);
        $this->assertEquals('izin', $presensi->first()->status);

        tenancy()->end();
    }

    public function test_ajukan_setujui_tolak_izin(): void
    {
        tenancy()->initialize($this->tenantA);

        $user = User::factory()->create(['tenant_id' => 'pesantren-a']);

        $izin = $this->service->ajukanIzin($this->santriA, [
            'jenis_izin' => 'pulang',
            'tanggal_mulai' => today()->toDateString(),
            'tanggal_selesai' => today()->addDays(2)->toDateString(),
            'alasan' => 'Acara keluarga',
        ]);

        $this->assertEquals('pending', $izin->status);

        $disetujui = $this->service->setujuiIzin($izin, $user->id, 'OK diizinkan');
        $this->assertEquals('disetujui', $disetujui->status);

        tenancy()->end();
    }

    public function test_konfirmasi_kembali_santri(): void
    {
        tenancy()->initialize($this->tenantA);

        $user = User::factory()->create(['tenant_id' => 'pesantren-a']);
        $izin = $this->service->ajukanIzin($this->santriA, [
            'jenis_izin' => 'pulang',
            'tanggal_mulai' => today()->toDateString(),
            'tanggal_selesai' => today()->addDays(1)->toDateString(),
            'alasan' => 'Pengobatan',
        ]);
        $this->service->setujuiIzin($izin, $user->id);
        $kembali = $this->service->konfirmasiKembali($izin);

        $this->assertTrue($kembali->sudah_kembali);
        $this->assertNotNull($kembali->tanggal_kembali_aktual);

        tenancy()->end();
    }

    public function test_izin_tenant_a_tidak_bocor_ke_tenant_b(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->service->ajukanIzin($this->santriA, [
            'jenis_izin' => 'sakit',
            'tanggal_mulai' => today()->toDateString(),
            'tanggal_selesai' => today()->toDateString(),
            'alasan' => 'Demam',
        ]);
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $this->assertCount(0, IzinSantri::all());
        tenancy()->end();
    }

    public function test_catat_kunjungan_wali(): void
    {
        tenancy()->initialize($this->tenantA);

        $kunjungan = $this->service->catatKunjungan($this->santriA, [
            'nama_pengunjung' => 'Bapak Usman',
            'hubungan' => 'ayah',
            'tanggal' => today()->toDateString(),
            'keperluan' => 'Mengantarkan perlengkapan',
        ]);

        $this->assertInstanceOf(KunjunganWali::class, $kunjungan);
        $this->assertEquals('Bapak Usman', $kunjungan->nama_pengunjung);

        tenancy()->end();
    }
}
