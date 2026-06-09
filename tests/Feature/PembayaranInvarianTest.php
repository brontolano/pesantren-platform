<?php

namespace Tests\Feature;

use App\Models\User;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\PembayaranService;
use Modules\Keuangan\Services\TagihanService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Pembuktian invarian logika bisnis pembayaran yang BELUM tercakup test lain:
 *  - I-3 (#25) State-machine: transisi/aksi ilegal ditolak di service-layer.
 *  - I-4 (#30) Idempotensi uang: verifikasi ganda tak menggandakan.
 *
 * Tidak menduplikasi coverage yang sudah ada (status recalc, input pada tagihan
 * lunas, jurnal idempotency_key, double-event auto-jurnal sudah teruji terpisah).
 *
 * Pakai pola shared-DB (InteractsWithPpdbTestDatabase) — bukan RefreshDatabase —
 * karena CREATE DATABASE per-tenant tak bisa jalan di dalam transaksi.
 */
class PembayaranInvarianTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'kelas', 'tagihan', 'pembayaran',
        ]);

        $this->truncatePpdbTables([
            'pembayaran', 'tagihan', 'santri', 'kelas', 'domains', 'tenants', 'users',
        ]);

        $tenant = $this->createRuntimeTenant('inv-keu', 'Pesantren Invarian');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function tagihan(float $nominal = 300000): Tagihan
    {
        $santri = Santri::create([
            'nis' => 'INV001',
            'nama' => 'Santri Invarian',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);

        return (new TagihanService())->buatTagihanUntukSantri($santri, [
            'jenis_tagihan' => 'spp',
            'nominal' => $nominal,
            'jatuh_tempo' => '2026-07-10',
            'bulan_masehi' => '2026-07',
        ]);
    }

    private function verifier(): User
    {
        return User::create([
            'name' => 'Bendahara',
            'email' => 'bendahara-inv@example.test',
            'password' => bcrypt('secret123'),
        ]);
    }

    // ─── I-3 (#25) State-machine ───────────────────────────────────────────

    public function test_i3_input_pada_tagihan_batal_ditolak(): void
    {
        $tagihan = $this->tagihan();
        $tagihan->update(['status' => 'batal']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('dibatalkan');

        (new PembayaranService())->inputPembayaran($tagihan->fresh(), ['nominal' => 50000]);
    }

    public function test_i3_input_melebihi_sisa_tagihan_ditolak(): void
    {
        $tagihan = $this->tagihan(300000);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('melebihi sisa tagihan');

        (new PembayaranService())->inputPembayaran($tagihan, ['nominal' => 400000]);
    }

    public function test_i3_input_nominal_nol_ditolak(): void
    {
        $tagihan = $this->tagihan(300000);

        $this->expectException(InvalidArgumentException::class);

        (new PembayaranService())->inputPembayaran($tagihan, ['nominal' => 0]);
    }

    public function test_i3_tolak_pembayaran_yang_sudah_terverifikasi_ditolak(): void
    {
        $tagihan = $this->tagihan(300000);
        $svc = new PembayaranService();
        $verifier = $this->verifier();

        $bayar = $svc->inputPembayaran($tagihan, ['nominal' => 100000]);
        $svc->verifikasiPembayaran($bayar, $verifier->id);

        $this->expectException(InvalidArgumentException::class);

        $svc->tolakPembayaran($bayar->fresh(), $verifier->id, 'coba tolak setelah verifikasi');
    }

    // ─── I-4 (#30) Idempotensi operasi uang ────────────────────────────────

    public function test_i4_verifikasi_dua_kali_ditolak_tanpa_duplikasi(): void
    {
        $tagihan = $this->tagihan(300000);
        $svc = new PembayaranService();
        $verifier = $this->verifier();

        $bayar = $svc->inputPembayaran($tagihan, ['nominal' => 300000]);
        $svc->verifikasiPembayaran($bayar, $verifier->id);

        $tagihan->refresh();
        $this->assertSame('lunas', $tagihan->status);

        // Replay verifikasi → ditolak (guard is_verified).
        try {
            $svc->verifikasiPembayaran($bayar->fresh(), $verifier->id);
            $this->fail('Verifikasi kedua seharusnya dilempar InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            // diharapkan
        }

        // Tidak ada penggandaan: tetap satu pembayaran terverifikasi, status stabil.
        $this->assertSame(
            1,
            Pembayaran::query()->where('tagihan_id', $tagihan->id)->where('is_verified', true)->count()
        );
        $tagihan->refresh();
        $this->assertSame('lunas', $tagihan->status);
    }
}
