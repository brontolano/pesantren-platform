<?php

namespace Tests\Feature;

use App\Models\AccessWali;
use App\Models\PortalWaliIzin;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\Rapor;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\PenempatanKelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\TabunganTransaksi;
use Modules\Keuangan\Models\Tagihan;
use Modules\Notifikasi\Models\LogNotifikasi;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PortalWaliFeatureTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('portal_wali_izin')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables([
            'portal_wali_izin',
            'access_wali',
            'log_notifikasi',
            'broadcast_wa',
            'template_notifikasi',
            'tabungan_transaksi',
            'tabungan',
            'pembayaran',
            'tagihan',
            'rapor',
            'nilai',
            'jadwal_pelajaran',
            'pelajaran',
            'periode_akademik',
            'penempatan_kelas',
            'status_santri',
            'alumni',
            'santri',
            'kelas',
            'settings',
            'role_has_permissions',
            'model_has_roles',
            'model_has_permissions',
            'roles',
            'permissions',
            'users',
            'domains',
            'tenants',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_wali_can_view_portal_pages_submit_izin_and_download_rapor(): void
    {
        [$tenant, $wali, $santri, $rapor] = $this->seedPortalContext();

        $dashboardResponse = $this
            ->actingAs($wali)
            ->get("http://{$tenant->id}.localhost/wali");

        $dashboardResponse->assertOk();
        $dashboardResponse->assertInertia(fn (Assert $page) => $page
            ->component('PortalWali/Dashboard')
            ->where('summary.childrenCount', 1)
            ->where('summary.activeChildrenCount', 1)
            ->where('children.0.nama', 'Santri Portal')
            ->where('navigation.0.label', 'Beranda')
        );

        $profileResponse = $this
            ->actingAs($wali)
            ->get("http://{$tenant->id}.localhost/wali/santri/{$santri->id}");

        $profileResponse->assertOk();
        $profileResponse->assertInertia(fn (Assert $page) => $page
            ->component('PortalWali/Profile')
            ->where('child.nama', 'Santri Portal')
            ->where('child.kelas.nama', 'IX-A')
            ->has('placementHistory', 1)
        );

        $academicResponse = $this
            ->actingAs($wali)
            ->get("http://{$tenant->id}.localhost/wali/santri/{$santri->id}/akademik");

        $academicResponse->assertOk();
        $academicResponse->assertInertia(fn (Assert $page) => $page
            ->component('PortalWali/Academic')
            ->where('child.nama', 'Santri Portal')
            ->where('latestRapor.ipk', 92.5)
            ->where('attendance.totalDisetujui', 1)
            ->has('grades', 1)
            ->has('schedule', 1)
        );

        $financialResponse = $this
            ->actingAs($wali)
            ->get("http://{$tenant->id}.localhost/wali/santri/{$santri->id}/keuangan");

        $financialResponse->assertOk();
        $financialResponse->assertInertia(fn (Assert $page) => $page
            ->component('PortalWali/Financial')
            ->where('child.nama', 'Santri Portal')
            ->where('summary.tagihanBelumLunas', 1)
            ->where('summary.totalOutstanding', 350000)
            ->where('savings.saldo', 125000)
            ->has('payments', 1)
        );

        $notificationsResponse = $this
            ->actingAs($wali)
            ->get("http://{$tenant->id}.localhost/wali/notifikasi");

        $notificationsResponse->assertOk();
        $notificationsResponse->assertInertia(fn (Assert $page) => $page
            ->component('PortalWali/Notifications')
            ->where('summary.sentCount', 1)
            ->where('summary.failedCount', 0)
            ->has('notifications', 1)
        );

        $izinResponse = $this
            ->actingAs($wali)
            ->post("http://{$tenant->id}.localhost/wali/izin", [
                'santri_id' => $santri->id,
                'jenis_izin' => 'pulang',
                'tanggal_mulai' => '2026-06-05',
                'tanggal_selesai' => '2026-06-06',
                'alasan' => 'Menjenguk keluarga',
                'catatan_wali' => 'Kembali ba`da ashar.',
            ]);

        $izinResponse->assertRedirect("http://{$tenant->id}.localhost/wali/izin");

        $this->assertDatabaseHas('portal_wali_izin', [
            'tenant_id' => $tenant->id,
            'santri_id' => $santri->id,
            'wali_id' => $wali->id,
            'jenis_izin' => 'pulang',
            'status' => 'pending',
        ]);

        $izinPageResponse = $this
            ->actingAs($wali)
            ->get("http://{$tenant->id}.localhost/wali/izin");

        $izinPageResponse->assertOk();
        $izinPageResponse->assertInertia(fn (Assert $page) => $page
            ->component('PortalWali/Izin')
            ->has('submissions', 2)
        );

        $raporDownloadResponse = $this
            ->actingAs($wali)
            ->get("http://{$tenant->id}.localhost/wali/santri/{$santri->id}/rapor/{$rapor->id}/download");

        $raporDownloadResponse->assertOk();
        $raporDownloadResponse->assertSee('Santri Portal');
        $raporDownloadResponse->assertSee('Rapor Akademik');
    }

    public function test_wali_cannot_access_other_child_or_other_tenant_records(): void
    {
        [$tenant, $wali, $santri] = $this->seedPortalContext();
        [$otherTenant, $otherWali, $otherSantri, $otherRapor] = $this->seedPortalContext('portal-wali-b', 'Wali Lain', 'PWL200');

        $otherChildSameTenant = Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => 'PWL999',
            'nama' => 'Santri Lain',
            'tanggal_lahir' => '2013-02-01',
            'jenis_kelamin' => 'P',
            'status' => 'aktif',
            'kelas_id' => $santri->kelas_id,
        ]);

        $this->actingAs($wali)
            ->get("http://{$tenant->id}.localhost/wali/santri/{$otherChildSameTenant->id}")
            ->assertNotFound();

        $this->actingAs($wali)
            ->get("http://{$tenant->id}.localhost/wali/santri/{$otherSantri->id}/keuangan")
            ->assertNotFound();

        $this->actingAs($wali)
            ->get("http://{$tenant->id}.localhost/wali/santri/{$santri->id}/rapor/{$otherRapor->id}/download")
            ->assertNotFound();

        $this->actingAs($otherWali)
            ->get("http://{$otherTenant->id}.localhost/wali/santri/{$santri->id}")
            ->assertNotFound();
    }

    public function test_wali_dashboard_loads_under_two_hundred_milliseconds_for_small_payload(): void
    {
        [$tenant, $wali] = $this->seedPortalContext('portal-wali-fast', 'Wali Cepat', 'PWL300');

        $startedAt = microtime(true);

        $response = $this
            ->actingAs($wali)
            ->get("http://{$tenant->id}.localhost/wali");

        $duration = microtime(true) - $startedAt;

        $response->assertOk();
        $this->assertLessThan(0.2, $duration, "Dashboard Portal Wali melebihi 200ms: {$duration}");
    }

    protected function seedPortalContext(
        string $tenantId = 'portal-wali-a',
        string $waliName = 'Wali Portal',
        string $nis = 'PWL100',
    ): array {
        $tenant = $this->createTestTenant($tenantId, strtoupper($tenantId), $tenantId);
        tenancy()->initialize($tenant);

        $kelas = Kelas::create([
            'tenant_id' => $tenant->id,
            'nama_kelas' => 'IX-A',
            'tingkat' => '9',
            'capacity' => 32,
            'is_active' => true,
        ]);

        $wali = User::create([
            'tenant_id' => $tenant->id,
            'name' => $waliName,
            'email' => "{$tenantId}@test.local",
            'password' => 'password',
        ]);
        $wali->assignRole('wali');

        $santri = Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => $nis,
            'nama' => 'Santri Portal',
            'tanggal_lahir' => '2012-04-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => $kelas->id,
            'wali_user_id' => $wali->id,
            'alamat' => 'Jl. Pesantren No. 1',
            'nama_ayah' => 'Ayah Portal',
            'nama_ibu' => 'Ibu Portal',
            'no_hp_wali' => '081234567890',
            'email_wali' => $wali->email,
        ]);

        AccessWali::create([
            'tenant_id' => $tenant->id,
            'wali_id' => $wali->id,
            'santri_id' => $santri->id,
            'relationship_type' => 'ayah',
            'is_primary' => true,
        ]);

        PenempatanKelas::create([
            'tenant_id' => $tenant->id,
            'santri_id' => $santri->id,
            'kelas_id' => $kelas->id,
            'semester' => 'genap',
            'tahun_akademik' => '2026/2027',
            'tanggal_penempatan' => '2026-01-05',
            'is_current' => true,
        ]);

        $periode = PeriodeAkademik::create([
            'tenant_id' => $tenant->id,
            'nama' => 'Semester Genap',
            'tahun_akademik' => '2026/2027',
            'semester' => 'genap',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_akhir' => '2026-06-30',
            'is_active' => true,
        ]);

        $guru = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Guru Portal',
            'email' => "guru-{$tenantId}@test.local",
            'password' => 'password',
        ]);
        $guru->assignRole('guru');

        $pelajaran = Pelajaran::create([
            'tenant_id' => $tenant->id,
            'nama' => 'Tafsir',
            'kode' => 'TAF',
            'sks' => 2,
            'guru_pengampu_id' => $guru->id,
        ]);

        JadwalPelajaran::create([
            'tenant_id' => $tenant->id,
            'kelas_id' => $kelas->id,
            'pelajaran_id' => $pelajaran->id,
            'periode_akademik_id' => $periode->id,
            'hari' => 'Senin',
            'jam_mulai' => '08:00',
            'jam_selesai' => '09:30',
            'ruangan' => 'Ruang 1',
        ]);

        Nilai::create([
            'tenant_id' => $tenant->id,
            'santri_id' => $santri->id,
            'pelajaran_id' => $pelajaran->id,
            'periode_akademik_id' => $periode->id,
            'kelas_id' => $kelas->id,
            'nilai_harian' => 90,
            'nilai_uts' => 92,
            'nilai_uas' => 95,
            'nilai_akhir' => 92.5,
            'input_by' => $guru->id,
        ]);

        $rapor = Rapor::create([
            'tenant_id' => $tenant->id,
            'santri_id' => $santri->id,
            'periode_akademik_id' => $periode->id,
            'total_sks' => 2,
            'ipk' => 92.5,
            'rank_kelas' => 1,
            'catatan_walikelas' => 'Santri disiplin dan konsisten.',
            'ringkasan_nilai' => [
                ['pelajaran' => 'Tafsir', 'nilai_akhir' => 92.5],
            ],
            'generated_at' => now(),
        ]);

        $tagihan = Tagihan::create([
            'tenant_id' => $tenant->id,
            'santri_id' => $santri->id,
            'jenis_tagihan' => 'SPP',
            'keterangan' => 'SPP Juni 2026',
            'nominal' => 500000,
            'diskon' => 0,
            'nominal_akhir' => 500000,
            'jatuh_tempo' => '2026-06-10',
            'status' => 'sebagian',
            'created_by' => $guru->id,
        ]);

        Pembayaran::create([
            'tenant_id' => $tenant->id,
            'tagihan_id' => $tagihan->id,
            'santri_id' => $santri->id,
            'nominal' => 150000,
            'metode' => 'transfer',
            'tanggal_pembayaran' => '2026-06-03',
            'is_verified' => true,
            'verified_by' => $guru->id,
            'created_by' => $guru->id,
        ]);

        $tabungan = Tabungan::create([
            'tenant_id' => $tenant->id,
            'santri_id' => $santri->id,
            'saldo' => 125000,
        ]);

        TabunganTransaksi::create([
            'tenant_id' => $tenant->id,
            'tabungan_id' => $tabungan->id,
            'tipe' => 'setor',
            'nominal' => 125000,
            'saldo_sebelum' => 0,
            'saldo_sesudah' => 125000,
            'keterangan' => 'Setoran awal',
            'tanggal' => '2026-06-01',
            'created_by' => $guru->id,
        ]);

        LogNotifikasi::create([
            'tenant_id' => $tenant->id,
            'jenis' => 'tagihan',
            'channel' => 'email',
            'target' => $wali->email,
            'status' => 'sent',
            'payload' => ['subject' => 'Tagihan baru'],
            'created_at' => now(),
        ]);

        PortalWaliIzin::create([
            'tenant_id' => $tenant->id,
            'santri_id' => $santri->id,
            'wali_id' => $wali->id,
            'jenis_izin' => 'sakit',
            'tanggal_mulai' => '2026-06-01',
            'tanggal_selesai' => '2026-06-01',
            'alasan' => 'Demam',
            'status' => 'approved',
            'catatan_admin' => 'Disetujui ustadz piket.',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        return [$tenant, $wali, $santri, $rapor];
    }
}
