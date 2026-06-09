<?php

namespace Tests\Feature;

use App\Models\User;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\PembayaranService;
use Modules\Keuangan\Services\TabunganService;
use Modules\Keuangan\Services\TagihanService;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Paket B (adaptasi Payment Entry ERPNext): satu pembayaran dialokasi ke banyak
 * Tagihan (FIFO jatuh tempo) + sisa lebih dititipkan ke Tabungan santri.
 */
class PembayaranAlokasiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'settings',
            'tagihan', 'pembayaran', 'tabungan', 'tabungan_transaksi',
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
            'nis' => 'ALO-'.str()->random(5), 'nama' => 'Santri Alokasi',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
    }

    private function tagihanSpp(Santri $santri, string $bulan, string $jatuhTempo, float $nominal): Tagihan
    {
        return (new TagihanService())->buatTagihanUntukSantri($santri, [
            'jenis_tagihan' => 'spp',
            'keterangan' => 'SPP '.$bulan,
            'nominal' => $nominal,
            'jatuh_tempo' => $jatuhTempo,
            'periode_akademik' => '2026/2027',
            'bulan_masehi' => $bulan,
        ]);
    }

    public function test_alokasi_fifo_melunasi_tagihan_terlama_lebih_dulu(): void
    {
        $this->boot('alo1');
        $user = User::factory()->create();
        $santri = $this->santri();

        $juli = $this->tagihanSpp($santri, '2026-07', '2026-07-10', 300000);
        $agt = $this->tagihanSpp($santri, '2026-08', '2026-08-10', 300000);
        $sept = $this->tagihanSpp($santri, '2026-09', '2026-09-10', 300000);

        // Bayar Rp700.000 → Juli & Agustus lunas, September terbayar 100.000.
        $hasil = app(PembayaranService::class)->alokasiPembayaran($santri, 700000, ['created_by' => $user->id]);

        $this->assertEqualsWithDelta(700000, $hasil['teralokasi'], 0.01);
        $this->assertEqualsWithDelta(0, $hasil['titipan'], 0.01);
        $this->assertSame('lunas', $juli->fresh()->status);
        $this->assertSame('lunas', $agt->fresh()->status);
        $this->assertSame('sebagian', $sept->fresh()->status);
        $this->assertEqualsWithDelta(200000, (float) $sept->fresh()->sisa_tagihan, 0.01);

        tenancy()->end();
    }

    public function test_kelebihan_pembayaran_jadi_titipan_di_tabungan(): void
    {
        $this->boot('alo2');
        $user = User::factory()->create();
        $santri = $this->santri();

        $this->tagihanSpp($santri, '2026-07', '2026-07-10', 300000);

        // Bayar Rp500.000 untuk tagihan 300.000 → 200.000 jadi titipan tabungan.
        $hasil = app(PembayaranService::class)->alokasiPembayaran($santri, 500000, ['created_by' => $user->id]);

        $this->assertEqualsWithDelta(300000, $hasil['teralokasi'], 0.01);
        $this->assertEqualsWithDelta(200000, $hasil['titipan'], 0.01);
        $this->assertEqualsWithDelta(200000, app(TabunganService::class)->getSaldo($santri), 0.01);

        tenancy()->end();
    }

    public function test_alokasi_menghormati_urutan_tagihan_ids_eksplisit(): void
    {
        $this->boot('alo3');
        $user = User::factory()->create();
        $santri = $this->santri();

        $juli = $this->tagihanSpp($santri, '2026-07', '2026-07-10', 300000);
        $sept = $this->tagihanSpp($santri, '2026-09', '2026-09-10', 300000);

        // Paksa bayar September dulu (bukan FIFO) lewat tagihan_ids.
        $hasil = app(PembayaranService::class)->alokasiPembayaran($santri, 300000, [
            'created_by' => $user->id,
            'tagihan_ids' => [$sept->id, $juli->id],
        ]);

        $this->assertSame('lunas', $sept->fresh()->status);
        $this->assertSame('belum_bayar', $juli->fresh()->status);
        $this->assertCount(1, $hasil['alokasi']);

        tenancy()->end();
    }
}
