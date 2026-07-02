<?php

namespace Tests\Feature;

use App\Models\User;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\TabunganTransaksi;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Models\TagihanRutin;
use Modules\Keuangan\Services\PembayaranService;
use Modules\Keuangan\Services\TagihanKomponenTabunganService;
use Modules\Keuangan\Services\TagihanRutinService;
use Modules\Keuangan\Support\SyahriahKomponen;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * #332 Syahriah multi-komponen: rincian komponen pada aturan Tagihan Rutin,
 * salinan rincian pada tagihan terbit, dan setoran otomatis porsi
 * `masuk_tabungan` ke Tabungan santri saat lunas (idempoten — jalur uang).
 */
class TagihanRutinKomponenTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'settings',
            'tagihan', 'tagihan_rutin', 'pembayaran',
            'tabungan', 'tabungan_transaksi',
            'akun', 'jurnal', 'jurnal_baris', 'sumber_dana',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        (new LedgerSeeder)->run();
        $this->seedTenantRow($tenant);
    }

    private function santri(): Santri
    {
        return Santri::create([
            'nis' => 'KOM-'.str()->random(5), 'nama' => 'Santri Komponen',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
    }

    /** @return array<int, array{nama:string, nominal:float, masuk_tabungan:bool}> */
    private function komponenSyahriah(): array
    {
        return [
            ['nama' => 'Syahriah (uang makan)', 'nominal' => 300000.0, 'masuk_tabungan' => false],
            ['nama' => 'Listrik', 'nominal' => 25000.0, 'masuk_tabungan' => false],
            ['nama' => 'Madrasah diniyah', 'nominal' => 50000.0, 'masuk_tabungan' => false],
            ['nama' => 'Tabungan wajib', 'nominal' => 25000.0, 'masuk_tabungan' => true],
        ];
    }

    private function aturan(array $komponen): TagihanRutin
    {
        return TagihanRutin::create([
            'nama' => 'Syahriah Bulanan',
            'jenis_tagihan' => 'spp',
            'frekuensi' => 'bulanan',
            'nominal' => SyahriahKomponen::total($komponen),
            'komponen' => $komponen,
            'target_tipe' => 'semua',
            'tanggal_terbit' => 1,
            'tempo_hari' => 10,
            'aktif' => true,
        ]);
    }

    public function test_helper_komponen_total_dan_porsi_tabungan(): void
    {
        $komponen = $this->komponenSyahriah();

        $this->assertEqualsWithDelta(400000, SyahriahKomponen::total($komponen), 0.01);
        $this->assertEqualsWithDelta(25000, SyahriahKomponen::porsiTabungan($komponen), 0.01);

        // Baris kosong / nominal 0 / negatif dibuang oleh normalisasi.
        $kotor = array_merge($komponen, [
            ['nama' => '', 'nominal' => 999],
            ['nama' => 'Nol', 'nominal' => 0],
            ['nama' => 'Minus', 'nominal' => -5000, 'masuk_tabungan' => true],
        ]);
        $this->assertCount(4, SyahriahKomponen::normalize($kotor));
        $this->assertEqualsWithDelta(400000, SyahriahKomponen::total($kotor), 0.01);
    }

    public function test_template_syahriah_menghasilkan_aturan_valid_dan_terbit(): void
    {
        $this->boot('kom1');
        $this->santri();

        // Preset template (dipakai aksi "Buat dari Template Syahriah").
        $template = SyahriahKomponen::templateSyahriah();
        $this->assertNotEmpty($template);
        $this->assertSame($template, SyahriahKomponen::normalize($template)); // sudah bersih
        $this->assertGreaterThan(0, SyahriahKomponen::porsiTabungan($template)); // ada komponen tabungan

        $aturan = $this->aturan($template);
        $this->assertEqualsWithDelta(SyahriahKomponen::total($template), (float) $aturan->nominal, 0.01);

        $hasil = (new TagihanRutinService)->terbitkan($aturan, '2026-07');
        $this->assertSame(1, $hasil['dibuat']);

        tenancy()->end();
    }

    public function test_terbit_membawa_rincian_komponen_dan_total_benar(): void
    {
        $this->boot('kom2');
        $santri = $this->santri();

        $aturan = $this->aturan($this->komponenSyahriah());
        (new TagihanRutinService)->terbitkan($aturan, '2026-07');

        $tagihan = Tagihan::query()->where('santri_id', $santri->id)->firstOrFail();

        $this->assertEqualsWithDelta(400000, (float) $tagihan->nominal, 0.01);
        $this->assertEqualsWithDelta(400000, (float) $tagihan->nominal_akhir, 0.01);
        $this->assertIsArray($tagihan->komponen);
        $this->assertCount(4, $tagihan->komponen);
        $this->assertSame('Tabungan wajib', $tagihan->komponen[3]['nama']);
        $this->assertTrue((bool) $tagihan->komponen[3]['masuk_tabungan']);
        $this->assertNull($tagihan->tabungan_disetor_at);

        tenancy()->end();
    }

    public function test_pelunasan_menyetor_porsi_tabungan_sekali_saja(): void
    {
        $this->boot('kom3');
        $user = User::factory()->create();
        $santri = $this->santri();

        $aturan = $this->aturan($this->komponenSyahriah());
        (new TagihanRutinService)->terbitkan($aturan, '2026-07');
        $tagihan = Tagihan::query()->where('santri_id', $santri->id)->firstOrFail();

        // Bayar penuh + verifikasi → lunas → porsi tabungan disetor otomatis.
        $svc = app(PembayaranService::class);
        $pembayaran = $svc->inputPembayaran($tagihan, [
            'nominal' => 400000, 'metode' => 'tunai', 'created_by' => $user->id,
        ]);
        $svc->verifikasiPembayaran($pembayaran, $user->id);

        $tagihan->refresh();
        $this->assertSame('lunas', $tagihan->status);
        $this->assertNotNull($tagihan->tabungan_disetor_at);

        $tabungan = Tabungan::query()->where('santri_id', $santri->id)->firstOrFail();
        $this->assertEqualsWithDelta(25000, (float) $tabungan->saldo, 0.01);
        $this->assertSame(1, TabunganTransaksi::query()->where('tabungan_id', $tabungan->id)->where('tipe', 'setor')->count());

        // IDEMPOTEN: pemicu ulang (recalculation status / retry event) tak setor ganda.
        $svc->updateStatusTagihan($tagihan->fresh());
        app(TagihanKomponenTabunganService::class)->setorPorsiTabungan($tagihan->fresh());

        $this->assertEqualsWithDelta(25000, (float) $tabungan->fresh()->saldo, 0.01);
        $this->assertSame(1, TabunganTransaksi::query()->where('tabungan_id', $tabungan->id)->where('tipe', 'setor')->count());

        tenancy()->end();
    }

    public function test_tagihan_lunas_tanpa_komponen_tabungan_tidak_membuat_setoran(): void
    {
        $this->boot('kom4');
        $user = User::factory()->create();
        $santri = $this->santri();

        $komponen = [
            ['nama' => 'Syahriah (uang makan)', 'nominal' => 300000.0, 'masuk_tabungan' => false],
        ];
        $aturan = $this->aturan($komponen);
        (new TagihanRutinService)->terbitkan($aturan, '2026-07');
        $tagihan = Tagihan::query()->where('santri_id', $santri->id)->firstOrFail();

        $svc = app(PembayaranService::class);
        $pembayaran = $svc->inputPembayaran($tagihan, ['nominal' => 300000, 'created_by' => $user->id]);
        $svc->verifikasiPembayaran($pembayaran, $user->id);

        $this->assertSame('lunas', $tagihan->fresh()->status);
        $this->assertNotNull($tagihan->fresh()->tabungan_disetor_at); // ditandai — tak dievaluasi ulang
        $this->assertSame(0, TabunganTransaksi::query()->count());

        tenancy()->end();
    }
}
