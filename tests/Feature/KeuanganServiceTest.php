<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\LaporanKeuanganService;
use Modules\Keuangan\Services\PembayaranService;
use Modules\Keuangan\Services\RekonsiliasiService;
use Modules\Keuangan\Services\TabunganService;
use Modules\Keuangan\Services\TagihanService;
use Tests\TestCase;

class KeuanganServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['id' => 'keuangan-a', 'name' => 'Pesantren A']);
        $this->tenantA->domains()->create(['domain' => 'keuangan-a']);

        $this->tenantB = Tenant::create(['id' => 'keuangan-b', 'name' => 'Pesantren B']);
        $this->tenantB->domains()->create(['domain' => 'keuangan-b']);
    }

    private function createSantri(string $nis = 'KEU001', string $nama = 'Santri Keuangan'): Santri
    {
        return Santri::create([
            'nis' => $nis,
            'nama' => $nama,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);
    }

    private function createTagihanForSantri(Santri $santri, float $nominal = 500000): Tagihan
    {
        return (new TagihanService)->buatTagihanUntukSantri($santri, [
            'jenis_tagihan' => 'spp',
            'nominal' => $nominal,
            'jatuh_tempo' => '2026-07-10',
            'bulan_masehi' => '2026-07',
        ]);
    }

    private function createVerifier(string $email = 'bendahara@example.test'): User
    {
        return User::create([
            'name' => 'Bendahara',
            'email' => $email,
            'password' => bcrypt('secret123'),
        ]);
    }

    // ─── T1.8.1: Create tagihan — nominal & jatuh tempo tepat ───

    public function test_create_tagihan_nominal_dan_jatuh_tempo_tepat(): void
    {
        tenancy()->initialize($this->tenantA);

        $santri = $this->createSantri();
        $tagihan = (new TagihanService)->buatTagihanUntukSantri($santri, [
            'jenis_tagihan' => 'spp',
            'keterangan' => 'SPP Juli',
            'nominal' => 400000,
            'diskon' => 50000,
            'jatuh_tempo' => '2026-07-10',
            'periode_akademik' => '2026/2027',
            'bulan_masehi' => '2026-07',
        ]);

        $this->assertEquals(350000.0, (float) $tagihan->nominal_akhir);
        $this->assertEquals('2026-07-10', $tagihan->jatuh_tempo->format('Y-m-d'));
        $this->assertSame('spp', $tagihan->jenis_tagihan);
        $this->assertSame('belum_bayar', $tagihan->status);

        tenancy()->end();
    }

    // ─── T1.8.2: Pembayaran — status update akurat ───

    public function test_pembayaran_status_update_belum_sebagian_lunas(): void
    {
        tenancy()->initialize($this->tenantA);

        $santri = $this->createSantri();
        $tagihan = $this->createTagihanForSantri($santri, 300000);

        $this->assertSame('belum_bayar', $tagihan->status);

        $svc = new PembayaranService;
        $verifier = $this->createVerifier();

        // Bayar sebagian
        $bayar1 = $svc->inputPembayaran($tagihan, [
            'nominal' => 100000,
            'metode' => 'tunai',
        ]);

        // Verifikasi bayar1
        $svc->verifikasiPembayaran($bayar1, $verifier->id);
        $tagihan->refresh();
        $this->assertSame('sebagian', $tagihan->status);

        // Bayar sisa
        $bayar2 = $svc->inputPembayaran($tagihan, [
            'nominal' => 200000,
            'metode' => 'transfer',
        ]);
        $svc->verifikasiPembayaran($bayar2, $verifier->id);
        $tagihan->refresh();
        $this->assertSame('lunas', $tagihan->status);

        tenancy()->end();
    }

    // ─── T1.8.3: Rekonsiliasi — total terkumpul = sum pembayaran ───

    public function test_rekonsiliasi_total_terkumpul_equals_sum_pembayaran(): void
    {
        tenancy()->initialize($this->tenantA);

        $santri = $this->createSantri();
        $tagihan = $this->createTagihanForSantri($santri, 500000);

        $svc = new PembayaranService;
        $verifier = $this->createVerifier('bendahara-rekon@example.test');
        $bayar = $svc->inputPembayaran($tagihan, ['nominal' => 200000]);
        $svc->verifikasiPembayaran($bayar, $verifier->id);

        $rekon = (new RekonsiliasiService)->rekonsiliasiPeriode(null, '2026-07');

        $this->assertEquals(500000.0, $rekon['total_tagihan']);
        $this->assertEquals(200000.0, $rekon['total_terkumpul_verified']);
        $this->assertEquals(300000.0, $rekon['sisa_piutang']);
        $this->assertEquals(40.0, $rekon['persentase_terkumpul']);

        tenancy()->end();
    }

    // ─── T1.8.4: Dual calendar — Hijriah & Masehi terpisah ───

    public function test_dual_calendar_mode_masehi_only(): void
    {
        tenancy()->initialize($this->tenantA);

        // Default mode = masehi
        $santri = $this->createSantri();
        $tagihan = (new TagihanService)->buatTagihanUntukSantri($santri, [
            'jenis_tagihan' => 'spp',
            'nominal' => 100000,
            'jatuh_tempo' => '2026-07-10',
        ]);

        $this->assertSame('2026-07', $tagihan->bulan_masehi);
        $this->assertNull($tagihan->bulan_hijriah);

        tenancy()->end();
    }

    public function test_dual_calendar_mode_hijriah_requires_bulan_hijriah(): void
    {
        tenancy()->initialize($this->tenantA);

        (new TagihanService)->setPeriodeKeuangan('hijriah');

        $santri = $this->createSantri('KEU002');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('bulan_hijriah');

        (new TagihanService)->buatTagihanUntukSantri($santri, [
            'jenis_tagihan' => 'spp',
            'nominal' => 100000,
            'jatuh_tempo' => '2026-07-10',
        ]);

        tenancy()->end();
    }

    public function test_dual_calendar_mode_keduanya(): void
    {
        tenancy()->initialize($this->tenantA);

        (new TagihanService)->setPeriodeKeuangan('keduanya');

        $santri = $this->createSantri('KEU003');
        $tagihan = (new TagihanService)->buatTagihanUntukSantri($santri, [
            'jenis_tagihan' => 'spp',
            'nominal' => 100000,
            'jatuh_tempo' => '2026-07-10',
            'bulan_hijriah' => 'Muharram 1448',
        ]);

        $this->assertSame('2026-07', $tagihan->bulan_masehi);
        $this->assertSame('Muharram 1448', $tagihan->bulan_hijriah);

        tenancy()->end();
    }

    // ─── T1.8.5: Isolasi tenant — tagihan Tenant A tidak bisa dibayar dari Tenant B ───

    public function test_isolasi_tenant_tagihan_tidak_terlihat_lintas_tenant(): void
    {
        // Tenant A buat tagihan
        tenancy()->initialize($this->tenantA);
        $santriA = $this->createSantri('ISO001', 'Santri Tenant A');
        $this->createTagihanForSantri($santriA, 250000);
        $tagihanCountA = Tagihan::count();
        tenancy()->end();

        // Tenant B — harus kosong
        tenancy()->initialize($this->tenantB);
        $tagihanCountB = Tagihan::count();
        tenancy()->end();

        $this->assertSame(1, $tagihanCountA);
        $this->assertSame(0, $tagihanCountB);
    }

    // ─── Tabungan service ───

    public function test_tabungan_setor_dan_tarik(): void
    {
        tenancy()->initialize($this->tenantA);

        $santri = $this->createSantri('TAB001');
        $svc = new TabunganService;

        // Setor
        $trx1 = $svc->setor($santri, 500000, ['keterangan' => 'Setoran awal']);
        $this->assertEquals(500000.0, (float) $trx1->saldo_sesudah);
        $this->assertSame('setor', $trx1->tipe);

        // Tarik
        $trx2 = $svc->tarik($santri, 150000);
        $this->assertEquals(350000.0, (float) $trx2->saldo_sesudah);

        // Saldo
        $this->assertEquals(350000.0, $svc->getSaldo($santri));

        tenancy()->end();
    }

    public function test_tabungan_tarik_melebihi_saldo_ditolak(): void
    {
        tenancy()->initialize($this->tenantA);

        $santri = $this->createSantri('TAB002');
        $svc = new TabunganService;
        $svc->setor($santri, 100000);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Saldo tidak cukup');

        $svc->tarik($santri, 200000);

        tenancy()->end();
    }

    // ─── Laporan keuangan ───

    public function test_laporan_harian_menghitung_pembayaran_terverifikasi(): void
    {
        tenancy()->initialize($this->tenantA);

        $santri = $this->createSantri('LAP001');
        $tagihan = $this->createTagihanForSantri($santri, 300000);

        $svc = new PembayaranService;
        $verifier = $this->createVerifier('bendahara-laporan@example.test');
        $bayar = $svc->inputPembayaran($tagihan, [
            'nominal' => 100000,
            'tanggal_pembayaran' => now()->toDateString(),
        ]);
        $svc->verifikasiPembayaran($bayar, $verifier->id);

        // Bayar kedua belum diverifikasi — jangan masuk laporan
        $svc->inputPembayaran($tagihan, [
            'nominal' => 50000,
            'tanggal_pembayaran' => now()->toDateString(),
        ]);

        $laporan = (new LaporanKeuanganService)->laporanHarian();

        $this->assertEquals(100000.0, $laporan['total_pembayaran']);
        $this->assertSame(1, $laporan['jumlah_transaksi']);

        tenancy()->end();
    }

    // ─── Pembayaran: tolak bayar tagihan lunas ───

    public function test_pembayaran_tolak_bayar_tagihan_lunas(): void
    {
        tenancy()->initialize($this->tenantA);

        $santri = $this->createSantri('TOL001');
        $tagihan = $this->createTagihanForSantri($santri, 100000);

        $svc = new PembayaranService;
        $verifier = $this->createVerifier('bendahara-lunas@example.test');
        $bayar = $svc->inputPembayaran($tagihan, ['nominal' => 100000]);
        $svc->verifikasiPembayaran($bayar, $verifier->id);
        $tagihan->refresh();

        $this->assertSame('lunas', $tagihan->status);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sudah lunas');

        $svc->inputPembayaran($tagihan, ['nominal' => 50000]);

        tenancy()->end();
    }
}
