<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\PembayaranService;
use Modules\Keuangan\Services\RekonsiliasiService;
use Modules\Keuangan\Services\TagihanService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Invarian I-7 (#31) — rekonsiliasi harian: deteksi & alarm anomali status.
 * Membuktikan status drift (status tersimpan ≠ status seharusnya) terdeteksi.
 */
class RekonsiliasiAnomaliTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'kelas', 'tagihan', 'pembayaran',
        ]);

        $this->truncatePpdbTables([
            'pembayaran', 'tagihan', 'santri', 'kelas', 'domains', 'tenants', 'users',
        ]);

        $this->tenant = $this->createRuntimeTenant('rekon', 'Pesantren Rekon');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
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
            'nis' => 'REK001',
            'nama' => 'Santri Rekon',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);

        return (new TagihanService)->buatTagihanUntukSantri($santri, [
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
            'email' => 'bendahara-rek@example.test',
            'password' => bcrypt('secret123'),
        ]);
    }

    public function test_data_konsisten_tidak_ada_anomali(): void
    {
        $tagihan = $this->tagihan(300000);
        $svc = new PembayaranService;
        $verifier = $this->verifier();

        $bayar = $svc->inputPembayaran($tagihan, ['nominal' => 300000]);
        $svc->verifikasiPembayaran($bayar, $verifier->id);

        $tagihan->refresh();
        $this->assertSame('lunas', $tagihan->status);

        $this->assertSame([], (new RekonsiliasiService)->deteksiAnomaliStatus());
    }

    public function test_status_drift_terdeteksi(): void
    {
        $tagihan = $this->tagihan(300000);

        // Paksa drift: tandai lunas TANPA pembayaran (mensimulasikan bypass/data manual).
        $tagihan->update(['status' => 'lunas']);

        $anomali = (new RekonsiliasiService)->deteksiAnomaliStatus();

        $this->assertCount(1, $anomali);
        $this->assertSame($tagihan->id, $anomali[0]['tagihan_id']);
        $this->assertSame('lunas', $anomali[0]['status_tersimpan']);
        $this->assertSame('belum_bayar', $anomali[0]['status_seharusnya']);
    }

    public function test_command_mendeteksi_anomali_dan_exit_sukses(): void
    {
        $tagihan = $this->tagihan(300000);
        $tagihan->update(['status' => 'lunas']); // drift

        tenancy()->end(); // command berjalan dari konteks central (seperti scheduler).

        $this->artisan('keuangan:rekonsiliasi-harian', ['tenant' => $this->tenant->id])
            ->assertExitCode(0);
    }

    public function test_command_tenant_tak_dikenal_gagal_anggun(): void
    {
        tenancy()->end();

        $this->artisan('keuangan:rekonsiliasi-harian', ['tenant' => 'tidak-ada-xyz'])
            ->assertExitCode(1);
    }
}
