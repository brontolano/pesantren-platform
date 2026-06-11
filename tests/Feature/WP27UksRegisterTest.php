<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Kesehatan\Models\KunjunganUks;
use Modules\Kesehatan\Services\KesehatanService;
use Modules\Kesantrian\Models\Santri;
use Tests\TestCase;

/**
 * WP-27 — Register UKS (kesehatan) — Test suite lengkap.
 * - Catat kunjungan santri ke UKS
 * - Auto-sync status istirahat → PresensiSantri sakit
 * - Tutup kunjungan (jam keluar)
 * - Daftar istirahat & rujukan
 * - Notifikasi ke wali saat rujukan
 */
class WP27UksRegisterTest extends TestCase
{
    use RefreshDatabase;

    private KesehatanService $service;
    private Tenant $tenant;
    private User $guru;
    private Santri $santri1;
    private Santri $santri2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KesehatanService();

        // Setup tenant & users
        $this->tenant = Tenant::create([
            'id' => 'pesantren-test',
            'name' => 'Pesantren Test',
            'is_active' => true,
        ]);

        tenancy()->initialize($this->tenant);

        $this->guru = User::create([
            'tenant_id' => 'pesantren-test',
            'name' => 'Guru Kesehatan',
            'email' => 'guru@pesantren.test',
            'password' => bcrypt('password'),
        ]);
        $this->guru->assignRole('guru');

        // Create santri
        $this->santri1 = Santri::create([
            'tenant_id' => 'pesantren-test',
            'nis' => '2024001',
            'nama' => 'Ahmad Santri',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'no_hp_wali' => '081234567890',
        ]);

        $this->santri2 = Santri::create([
            'tenant_id' => 'pesantren-test',
            'nis' => '2024002',
            'nama' => 'Budi Santri',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'no_hp_wali' => '081234567891',
        ]);

        tenancy()->end();
    }

    // ── Test Catat Kunjungan ────────────────────────────────────────────────

    public function test_catat_kunjungan_uks_status_pulang(): void
    {
        tenancy()->initialize($this->tenant);

        $kunjungan = $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'pulang',
            [
                'keluhan' => 'Sakit kepala ringan',
                'tindakan' => 'Istirahat dan minum air putih',
                'obat' => 'Paracetamol 500mg',
                'pencatat_id' => $this->guru->id,
            ]
        );

        $this->assertInstanceOf(KunjunganUks::class, $kunjungan);
        $this->assertEquals('pulang', $kunjungan->status);
        $this->assertEquals('Sakit kepala ringan', $kunjungan->keluhan);
        $this->assertNotNull($kunjungan->jam_masuk);
        $this->assertNull($kunjungan->jam_keluar);

        tenancy()->end();
    }

    public function test_catat_kunjungan_uks_status_istirahat(): void
    {
        tenancy()->initialize($this->tenant);

        $kunjungan = $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'istirahat',
            [
                'keluhan' => 'Demam tinggi 38°C',
                'tindakan' => 'Istirahat di UKS',
                'obat' => 'Paracetamol 500mg + Vitamin C',
                'pencatat_id' => $this->guru->id,
            ]
        );

        $this->assertEquals('istirahat', $kunjungan->status);

        tenancy()->end();
    }

    public function test_catat_kunjungan_uks_status_rujuk(): void
    {
        tenancy()->initialize($this->tenant);

        $kunjungan = $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'rujuk',
            [
                'keluhan' => 'Fraktur tungkai',
                'tindakan' => 'Pertolongan pertama & imobilisasi',
                'rujukan_ke' => 'RSUD Sumedang',
                'rujukan_biaya' => 150000,
                'pencatat_id' => $this->guru->id,
            ]
        );

        $this->assertEquals('rujuk', $kunjungan->status);
        $this->assertEquals('RSUD Sumedang', $kunjungan->rujukan_ke);
        $this->assertEquals(150000, $kunjungan->rujukan_biaya);

        tenancy()->end();
    }

    // ── Test Auto-Sync PresensiSantri ───────────────────────────────────────

    public function test_istirahat_uks_auto_create_presensi_sakit(): void
    {
        tenancy()->initialize($this->tenant);

        // Catat kunjungan status istirahat
        $kunjungan = $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'istirahat',
            [
                'keluhan' => 'Demam',
                'pencatat_id' => $this->guru->id,
            ]
        );

        // Periksa PresensiSantri otomatis dibuat dengan status sakit
        $presensi = PresensiSantri::where('santri_id', $this->santri1->id)
            ->where('tanggal', now()->toDateString())
            ->first();

        $this->assertNotNull($presensi);
        $this->assertEquals('sakit', $presensi->status);
        $this->assertStringContainsString('UKS: Demam', $presensi->keterangan);

        tenancy()->end();
    }

    public function test_pulang_uks_remove_presensi_sakit(): void
    {
        tenancy()->initialize($this->tenant);

        // Catat istirahat dulu
        $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'istirahat',
            ['keluhan' => 'Demam', 'pencatat_id' => $this->guru->id]
        );

        // Verifikasi presensi sakit ada
        $this->assertNotNull(
            PresensiSantri::where('santri_id', $this->santri1->id)
                ->where('status', 'sakit')
                ->first()
        );

        // Update ke status pulang
        $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'pulang',
            ['keluhan' => 'Sudah baikan', 'pencatat_id' => $this->guru->id]
        );

        // Presensi sakit harus dihapus
        $this->assertNull(
            PresensiSantri::where('santri_id', $this->santri1->id)
                ->where('status', 'sakit')
                ->first()
        );

        tenancy()->end();
    }

    public function test_rujuk_uks_remove_presensi_sakit(): void
    {
        tenancy()->initialize($this->tenant);

        // Catat istirahat dulu
        $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'istirahat',
            ['keluhan' => 'Demam', 'pencatat_id' => $this->guru->id]
        );

        // Update ke status rujuk
        $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'rujuk',
            [
                'keluhan' => 'Demam tidak turun',
                'rujukan_ke' => 'RSUD',
                'pencatat_id' => $this->guru->id,
            ]
        );

        // Presensi sakit harus dihapus
        $this->assertNull(
            PresensiSantri::where('santri_id', $this->santri1->id)
                ->where('status', 'sakit')
                ->first()
        );

        tenancy()->end();
    }

    // ── Test Idempotency (Partial Unique) ────────────────────────────────────

    public function test_catat_kunjungan_idempoten_per_santri_per_tanggal(): void
    {
        tenancy()->initialize($this->tenant);

        // Catat pertama
        $kunjungan1 = $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'pulang',
            ['keluhan' => 'Sakit kepala', 'pencatat_id' => $this->guru->id]
        );

        // Catat ulang (update)
        $kunjungan2 = $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'istirahat',
            ['keluhan' => 'Demam tinggi', 'pencatat_id' => $this->guru->id]
        );

        // Harus record yang sama (ID sama)
        $this->assertEquals($kunjungan1->id, $kunjungan2->id);
        $this->assertEquals('istirahat', $kunjungan2->status);
        $this->assertEquals('Demam tinggi', $kunjungan2->keluhan);

        // Hanya 1 record per santri per tanggal
        $this->assertCount(1, KunjunganUks::where('santri_id', $this->santri1->id)->get());

        tenancy()->end();
    }

    // ── Test Tutup Kunjungan ────────────────────────────────────────────────

    public function test_tutup_kunjungan_set_jam_keluar(): void
    {
        tenancy()->initialize($this->tenant);

        $kunjungan = $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'istirahat',
            ['keluhan' => 'Demam', 'pencatat_id' => $this->guru->id]
        );

        $this->assertNull($kunjungan->jam_keluar);

        $jamKeluar = '14:30:00';
        $kunjunganTutup = $this->service->tutupKunjungan($kunjungan, $jamKeluar);

        $this->assertNotNull($kunjunganTutup->jam_keluar);
        $this->assertEquals($jamKeluar, $kunjunganTutup->jam_keluar->format('H:i:s'));

        tenancy()->end();
    }

    // ── Test Daftar Istirahat & Rujukan ─────────────────────────────────────

    public function test_daftar_istirahat_hanya_santri_istirahat_hari_ini(): void
    {
        tenancy()->initialize($this->tenant);

        // Catat istirahat santri1 hari ini
        $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'istirahat',
            ['keluhan' => 'Demam', 'pencatat_id' => $this->guru->id]
        );

        // Catat pulang santri2 hari ini
        $this->service->catatKunjungan(
            $this->santri2,
            now()->toDateString(),
            'pulang',
            ['keluhan' => 'Batuk', 'pencatat_id' => $this->guru->id]
        );

        $istirahat = $this->service->getDaftarIstirahat();

        $this->assertCount(1, $istirahat);
        $this->assertEquals($this->santri1->id, $istirahat->first()->santri_id);

        tenancy()->end();
    }

    public function test_daftar_rujukan_terbuka_hanya_status_rujuk_tanpa_jam_keluar(): void
    {
        tenancy()->initialize($this->tenant);

        // Catat rujukan santri1 (belum tutup)
        $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'rujuk',
            [
                'keluhan' => 'Fraktur',
                'rujukan_ke' => 'RSUD',
                'pencatat_id' => $this->guru->id,
            ]
        );

        // Catat rujukan santri2 & tutup
        $kunjungan2 = $this->service->catatKunjungan(
            $this->santri2,
            now()->toDateString(),
            'rujuk',
            [
                'keluhan' => 'Luka dalam',
                'rujukan_ke' => 'RSUD',
                'pencatat_id' => $this->guru->id,
            ]
        );
        $this->service->tutupKunjungan($kunjungan2);

        $rujukanTerbuka = $this->service->getDaftarRujukanTerbuka();

        // Hanya santri1 (belum tutup)
        $this->assertCount(1, $rujukanTerbuka);
        $this->assertEquals($this->santri1->id, $rujukanTerbuka->first()->santri_id);

        tenancy()->end();
    }

    // ── Test Riwayat Kunjungan ──────────────────────────────────────────────

    public function test_daftar_kunjungan_santri_30_hari_terakhir(): void
    {
        tenancy()->initialize($this->tenant);

        // Catat 3 kunjungan di hari berbeda
        $this->service->catatKunjungan(
            $this->santri1,
            now()->subDays(2)->toDateString(),
            'pulang',
            ['keluhan' => 'Sakit kepala', 'pencatat_id' => $this->guru->id]
        );

        $this->service->catatKunjungan(
            $this->santri1,
            now()->subDays(1)->toDateString(),
            'istirahat',
            ['keluhan' => 'Demam', 'pencatat_id' => $this->guru->id]
        );

        $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'pulang',
            ['keluhan' => 'Batuk', 'pencatat_id' => $this->guru->id]
        );

        // Catat kunjungan santri2 (jangan dihitung)
        $this->service->catatKunjungan(
            $this->santri2,
            now()->toDateString(),
            'pulang',
            ['keluhan' => 'Sakit gigi', 'pencatat_id' => $this->guru->id]
        );

        $dari = now()->subDays(30)->toDateString();
        $sampai = now()->toDateString();

        $riwayat = $this->service->getDaftarKunjungan($this->santri1, $dari, $sampai);

        $this->assertCount(3, $riwayat);
        // Harus terurut terbaru
        $this->assertEquals(now()->toDateString(), $riwayat->first()->tanggal->toDateString());

        tenancy()->end();
    }

    public function test_get_kunjungan_hari_ini(): void
    {
        tenancy()->initialize($this->tenant);

        $kunjungan = $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'istirahat',
            ['keluhan' => 'Demam', 'pencatat_id' => $this->guru->id]
        );

        $hariIni = $this->service->getKunjunganHariIni($this->santri1);

        $this->assertNotNull($hariIni);
        $this->assertEquals($kunjungan->id, $hariIni->id);

        // Santri2 tidak ada
        $this->assertNull($this->service->getKunjunganHariIni($this->santri2));

        tenancy()->end();
    }

    // ── Test Validasi Input ─────────────────────────────────────────────────

    public function test_catat_kunjungan_status_invalid(): void
    {
        tenancy()->initialize($this->tenant);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'invalid_status',
            []
        );

        tenancy()->end();
    }

    // ── Test Tenant Isolation ───────────────────────────────────────────────

    public function test_kunjungan_tenant_a_tidak_bocor_ke_tenant_b(): void
    {
        tenancy()->initialize($this->tenant);

        $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'istirahat',
            ['keluhan' => 'Demam', 'pencatat_id' => $this->guru->id]
        );

        tenancy()->end();

        // Buat tenant baru
        $tenantB = Tenant::create([
            'id' => 'pesantren-b',
            'name' => 'Pesantren B',
            'is_active' => true,
        ]);

        tenancy()->initialize($tenantB);

        // Kunjungan harus kosong di tenant B
        $this->assertCount(0, KunjunganUks::all());
        $this->assertCount(0, PresensiSantri::all());

        tenancy()->end();
    }

    // ── Test API Endpoints ──────────────────────────────────────────────────

    public function test_api_catat_kunjungan_endpoint(): void
    {
        tenancy()->initialize($this->tenant);

        // Allow without explicit permission check (guru user assumed to have akses_pwa_guru)
        $this->guru->allow('akses_pwa_guru');

        tenancy()->end();

        $response = $this->actingAs($this->guru)->postJson('/api/pwa/guru/uks/catat-kunjungan', [
            'santri_id' => $this->santri1->id,
            'keluhan' => 'Sakit gigi',
            'tindakan' => 'Kumur air garam',
            'status' => 'pulang',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'santri_id',
            'tanggal',
            'jam_masuk',
            'status',
        ]);
    }

    public function test_api_daftar_istirahat_endpoint(): void
    {
        tenancy()->initialize($this->tenant);

        $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'istirahat',
            ['keluhan' => 'Demam', 'pencatat_id' => $this->guru->id]
        );

        // Allow permission
        $this->guru->allow('akses_pwa_guru');

        tenancy()->end();

        $response = $this->actingAs($this->guru)->getJson('/api/pwa/guru/uks/istirahat');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'santri_id',
                'santri_nama',
                'keluhan',
                'status',
            ],
        ]);
    }

    public function test_api_riwayat_endpoint(): void
    {
        tenancy()->initialize($this->tenant);

        $this->service->catatKunjungan(
            $this->santri1,
            now()->toDateString(),
            'istirahat',
            ['keluhan' => 'Demam', 'pencatat_id' => $this->guru->id]
        );

        // Allow permission
        $this->guru->allow('akses_pwa_guru');

        tenancy()->end();

        $response = $this->actingAs($this->guru)
            ->getJson("/api/pwa/guru/uks/{$this->santri1->id}/riwayat");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }
}
