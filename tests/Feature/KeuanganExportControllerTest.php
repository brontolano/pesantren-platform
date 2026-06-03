<?php

namespace Tests\Feature;

use App\Http\Controllers\KeuanganExportController;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Services\PembayaranService;
use Modules\Keuangan\Services\TagihanService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class KeuanganExportControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::create([
            'id' => 'tenant-export',
            'name' => 'Tenant Export',
            'is_active' => true,
        ]);
        $this->tenant->domains()->create(['domain' => 'tenant-export']);
    }

    public function test_tagihan_csv_hanya_memuat_data_tenant_aktif(): void
    {
        tenancy()->initialize($this->tenant);

        $user = $this->createFinanceUser('keuangan@tenant-export.test');
        $santri = $this->createSantri('EXP001', 'Ahmad Export');

        (new TagihanService())->buatTagihanUntukSantri($santri, [
            'jenis_tagihan' => 'spp',
            'keterangan' => 'SPP Juli',
            'nominal' => 450000,
            'diskon' => 50000,
            'jatuh_tempo' => '2026-07-10',
            'periode_akademik' => '2026/2027',
            'bulan_masehi' => '2026-07',
        ]);

        $this->actingAs($user);

        $response = app(KeuanganExportController::class)->tagihanCsv(new Request([
            'status' => 'belum_bayar',
        ]));

        $this->assertInstanceOf(StreamedResponse::class, $response);

        $content = $this->streamedContent($response);

        $this->assertStringContainsString('Nama Santri', $content);
        $this->assertStringContainsString('Ahmad Export', $content);
        $this->assertStringContainsString('SPP Juli', $content);

        tenancy()->end();
    }

    public function test_pembayaran_print_menampilkan_ringkasan_terverifikasi(): void
    {
        tenancy()->initialize($this->tenant);

        $user = $this->createFinanceUser('bendahara@tenant-export.test');
        $verifier = $this->createFinanceUser('verifier@tenant-export.test');
        $santri = $this->createSantri('EXP002', 'Fatimah Export');

        $tagihan = (new TagihanService())->buatTagihanUntukSantri($santri, [
            'jenis_tagihan' => 'spp',
            'nominal' => 300000,
            'jatuh_tempo' => '2026-07-15',
            'bulan_masehi' => '2026-07',
        ]);

        $pembayaran = (new PembayaranService())->inputPembayaran($tagihan, [
            'nominal' => 125000,
            'metode' => 'transfer',
            'nomor_bukti' => 'TRX-001',
            'tanggal_pembayaran' => now()->toDateString(),
        ]);

        (new PembayaranService())->verifikasiPembayaran($pembayaran, $verifier->id);

        $this->actingAs($user);

        $response = app(KeuanganExportController::class)->pembayaranPrint(new Request([
            'verified' => '1',
        ]));

        $html = $response->getContent();

        $this->assertStringContainsString('Laporan Pembayaran', $html);
        $this->assertStringContainsString('Fatimah Export', $html);
        $this->assertStringContainsString('TRX-001', $html);
        $this->assertStringContainsString('Verified', $html);

        tenancy()->end();
    }

    public function test_export_ditolak_untuk_user_tanpa_permission_keuangan(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::create([
            'name' => 'Wali Tenant',
            'email' => 'wali@tenant-export.test',
            'password' => bcrypt('secret123'),
        ]);
        $user->assignRole('wali');

        $this->actingAs($user);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(KeuanganExportController::class)->tagihanCsv(new Request());

        tenancy()->end();
    }

    private function createFinanceUser(string $email): User
    {
        $user = User::create([
            'name' => 'Bendahara Tenant',
            'email' => $email,
            'password' => bcrypt('secret123'),
        ]);
        $user->assignRole('keuangan');

        return $user;
    }

    private function createSantri(string $nis, string $nama): Santri
    {
        return Santri::create([
            'nis' => $nis,
            'nama' => $nama,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);
    }

    private function streamedContent(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }
}
