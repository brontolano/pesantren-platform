<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\TagihanService;
use Tests\TestCase;

class TagihanServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'tenant-tagihan',
            'name' => 'Tenant Tagihan',
        ]);
        $this->tenant->domains()->create(['domain' => 'tenant-tagihan']);
    }

    public function test_buat_tagihan_untuk_satu_santri_menghitung_nominal_akhir(): void
    {
        tenancy()->initialize($this->tenant);

        (new TagihanService)->setPeriodeKeuangan('masehi');

        $santri = Santri::create([
            'nis' => 'TAG001',
            'nama' => 'Santri Tagihan',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);

        $tagihan = (new TagihanService)->buatTagihanUntukSantri($santri, [
            'jenis_tagihan' => 'spp',
            'keterangan' => 'SPP Juli 2026',
            'nominal' => 350000,
            'diskon' => 50000,
            'jatuh_tempo' => '2026-07-10',
            'periode_akademik' => '2026/2027',
            'bulan_masehi' => '2026-07',
        ]);

        $this->assertSame('tenant-tagihan', $tagihan->tenant_id);
        $this->assertSame($santri->id, $tagihan->santri_id);
        $this->assertEquals(300000.0, (float) $tagihan->nominal_akhir);
        $this->assertEquals('2026-07-10', $tagihan->jatuh_tempo->format('Y-m-d'));
        $this->assertSame('2026-07', $tagihan->bulan_masehi);
        $this->assertNull($tagihan->bulan_hijriah);
        $this->assertDatabaseCount('tagihan', 1);

        tenancy()->end();
    }

    public function test_buat_tagihan_per_kelas_hanya_untuk_santri_aktif(): void
    {
        tenancy()->initialize($this->tenant);

        $kelas = Kelas::create([
            'nama_kelas' => 'VIII-A',
            'tingkat' => '8',
            'capacity' => 30,
        ]);

        $aktif = Santri::create([
            'nis' => 'TAG002',
            'nama' => 'Santri Aktif',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => $kelas->id,
        ]);

        Santri::create([
            'nis' => 'TAG003',
            'nama' => 'Santri Cuti',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'P',
            'status' => 'cuti',
            'kelas_id' => $kelas->id,
        ]);

        $records = (new TagihanService)->buatTagihanPerKelas($kelas, [
            'jenis_tagihan' => 'infaq',
            'nominal' => 100000,
            'jatuh_tempo' => '2026-07-15',
        ]);

        $this->assertCount(1, $records);
        $this->assertSame($aktif->id, $records->first()->santri_id);
        $this->assertDatabaseCount('tagihan', 1);

        tenancy()->end();
    }

    public function test_buat_tagihan_bulk_untuk_daftar_santri_tertentu(): void
    {
        tenancy()->initialize($this->tenant);

        (new TagihanService)->setPeriodeKeuangan('hijriah');

        $santriA = Santri::create([
            'nis' => 'TAG004',
            'nama' => 'Santri A',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);

        $santriB = Santri::create([
            'nis' => 'TAG005',
            'nama' => 'Santri B',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'P',
            'status' => 'aktif',
        ]);

        $records = (new TagihanService)->buatTagihanBulk([$santriA->id, $santriB->id], [
            'jenis_tagihan' => 'daftar_ulang',
            'nominal' => 200000,
            'jatuh_tempo' => '2026-08-01',
            'bulan_hijriah' => 'Safar 1448',
        ]);

        $this->assertCount(2, $records);
        $this->assertEqualsCanonicalizing(
            [$santriA->id, $santriB->id],
            Tagihan::query()->pluck('santri_id')->all()
        );
        $this->assertSame('Safar 1448', $records->last()->bulan_hijriah);
        $this->assertNull($records->first()->bulan_masehi);

        tenancy()->end();
    }

    public function test_mode_keduanya_menyimpan_masehi_dan_hijriah(): void
    {
        tenancy()->initialize($this->tenant);

        $service = new TagihanService;
        $service->setPeriodeKeuangan('keduanya');

        $santri = Santri::create([
            'nis' => 'TAG006',
            'nama' => 'Santri Kedua Kalender',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);

        $tagihan = $service->buatTagihanUntukSantri($santri, [
            'jenis_tagihan' => 'spp',
            'nominal' => 275000,
            'jatuh_tempo' => '2026-09-05',
            'bulan_hijriah' => 'Rabiul Awal 1448',
        ]);

        $this->assertSame('2026-09', $tagihan->bulan_masehi);
        $this->assertSame('Rabiul Awal 1448', $tagihan->bulan_hijriah);
        $this->assertSame('keduanya', Setting::query()->where('key', 'periode_keuangan')->first()?->value);

        tenancy()->end();
    }

    public function test_mode_hijriah_mewajibkan_bulan_hijriah(): void
    {
        tenancy()->initialize($this->tenant);

        $service = new TagihanService;
        $service->setPeriodeKeuangan('hijriah');

        $santri = Santri::create([
            'nis' => 'TAG007',
            'nama' => 'Santri Hijriah',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'P',
            'status' => 'aktif',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('membutuhkan `bulan_hijriah`');

        $service->buatTagihanUntukSantri($santri, [
            'jenis_tagihan' => 'spp',
            'nominal' => 150000,
            'jatuh_tempo' => '2026-09-10',
        ]);
    }
}
