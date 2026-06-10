<?php

namespace Tests\Feature;

use App\Models\AccessWali;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\AI\Models\AiApproval;
use Modules\AI\Models\AiTenantSetting;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Modules\AI\Services\Settings\AiTenantSettingDefaults;
use Modules\AI\Services\Settings\TenantSettingService;
use Modules\AI\Services\Tools\Adapters\AkademikToolAdapter;
use Modules\AI\Services\Tools\Adapters\LedgerToolAdapter;
use Modules\AI\Services\Tools\ToolRegistry;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use Modules\PPDB\Models\CalonSantri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A033 — tools data terstruktur resmi (READ-ONLY):
 *  - getTagihan nyata → nilai benar + cap_waktu + sumber + keTeks() template;
 *  - scope_data OFF → ditolak pesan jelas; ON → jalan;
 *  - WALI hanya anak tertaut AccessWali (santri_id orang lain ditolak);
 *  - isolasi tenant (data A tak terbaca dari konteks B);
 *  - args invalid / tool tak dikenal / peran tak diizinkan / tanpa identitas;
 *  - adapter READ-ONLY: tidak ada INSERT/UPDATE/DELETE selama call().
 */
class AiToolsDataTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'ai_tenant_setting', 'ai_approval', 'tagihan', 'pembayaran',
            'santri', 'access_wali', 'calon_santri',
        ]);
        $this->truncatePpdbTables([
            'domains', 'tenants', 'tagihan', 'pembayaran',
            'santri', 'access_wali', 'calon_santri',
        ]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    private function siapkanTenant(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);

        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        app(AiSettingProvisioner::class)->provision((string) $tenant->id);

        return $tenant;
    }

    private function buatUser(string $email, array $roles = [], array $permissions = []): User
    {
        $user = User::create([
            'name' => 'Uji '.$email,
            'email' => $email,
            'password' => 'password',
        ]);

        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    /** Aktifkan setting dengan override (alur approval kyai bila sensitif). */
    private function terapkanSetting(array $overrides): void
    {
        $admin = $this->buatUser('admin-'.uniqid().'@test.local', [], ['ai.setting.kelola']);
        $kyai = $this->buatUser('kyai-'.uniqid().'@test.local', [], ['ai.setting.setujui']);
        $service = app(TenantSettingService::class);

        $payload = array_replace(AiTenantSettingDefaults::konservatif(), $overrides);

        $draf = $service->simpanDraf($payload, $admin);
        $hasil = $service->ajukan($draf->versi, $admin);

        if ($hasil->status === AiTenantSetting::STATUS_PENDING_APPROVAL) {
            $approval = AiApproval::query()
                ->where('ai_tenant_setting_id', $draf->id)
                ->firstOrFail();
            $service->putuskan($approval->id, $kyai, true);
        }
    }

    private function registry(): ToolRegistry
    {
        return app(ToolRegistry::class);
    }

    private function buatSantri(string $nis, string $nama = 'Santri Uji'): Santri
    {
        return Santri::create([
            'nis' => $nis,
            'nama' => $nama,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);
    }

    private function buatTagihan(Santri $s, float $nominal, string $status = 'belum_bayar'): Tagihan
    {
        return Tagihan::create([
            'santri_id' => $s->id,
            'jenis_tagihan' => 'spp',
            'nominal' => $nominal,
            'nominal_akhir' => $nominal,
            'status' => $status,
            'jatuh_tempo' => '2026-07-01',
        ]);
    }

    // ------------------------------------------------------------------
    // getTagihan end-to-end nyata: nilai + cap_waktu + sumber + template
    // ------------------------------------------------------------------

    public function test_get_tagihan_mengembalikan_nilai_cap_waktu_dan_sumber(): void
    {
        $this->siapkanTenant('aitla');
        $admin = $this->buatUser('staf@test.local', ['admin_pesantren']);

        $santri = $this->buatSantri('AIT001');
        $this->buatTagihan($santri, 200000);
        $sebagian = $this->buatTagihan($santri, 100000, 'sebagian');
        $this->buatTagihan($santri, 50000, 'lunas'); // tidak boleh ikut

        // Pembayaran terverifikasi 40k → sisa tagihan 'sebagian' = 60k.
        Pembayaran::create([
            'tagihan_id' => $sebagian->id,
            'santri_id' => $santri->id,
            'nominal' => 40000,
            'metode' => 'tunai',
            'tanggal_pembayaran' => '2026-06-01',
            'is_verified' => true,
        ]);

        $hasil = $this->registry()->call('getTagihan', ['santri_id' => $santri->id], $admin);

        $this->assertTrue($hasil->sukses, (string) $hasil->pesan);
        $this->assertSame(2, $hasil->nilai['jumlah']);
        $this->assertEqualsWithDelta(260000.0, $hasil->nilai['total_sisa'], 0.01);
        $this->assertNotNull($hasil->capWaktu);
        $this->assertStringContainsString('Keuangan', (string) $hasil->sumber);

        // Formatter template TANPA LLM (Blueprint §7.1).
        $teks = $hasil->keTeks();
        $this->assertStringContainsString('Rp 260.000', $teks);
        $this->assertStringContainsString('jatuh tempo', $teks);
        $this->assertStringContainsString('Sumber:', $teks);
    }

    public function test_get_status_bayar_merangkum_lunas_dan_belum(): void
    {
        $this->siapkanTenant('aitlb');
        $admin = $this->buatUser('staf@test.local', ['admin_pesantren']);

        $santri = $this->buatSantri('AIT002');
        $this->buatTagihan($santri, 200000);
        $this->buatTagihan($santri, 50000, 'lunas');

        $hasil = $this->registry()->call('getStatusBayar', ['santri_id' => $santri->id], $admin);

        $this->assertTrue($hasil->sukses, (string) $hasil->pesan);
        $this->assertSame(1, $hasil->nilai['jumlah_lunas']);
        $this->assertSame(1, $hasil->nilai['jumlah_belum_lunas']);
        $this->assertEqualsWithDelta(250000.0, $hasil->nilai['total_tagihan'], 0.01);
    }

    // ------------------------------------------------------------------
    // Gerbang scope_data: OFF ditolak jelas, ON jalan
    // ------------------------------------------------------------------

    public function test_scope_data_off_ditolak_dan_on_jalan(): void
    {
        $this->siapkanTenant('aitlc');
        $admin = $this->buatUser('staf@test.local', ['admin_pesantren']);

        // Default konservatif: scope 'nilai' = OFF.
        $ditolak = $this->registry()->call('getNilai', ['santri_id' => 1], $admin);
        $this->assertFalse($ditolak->sukses);
        $this->assertStringContainsString('belum diaktifkan', (string) $ditolak->pesan);

        // Aktifkan scope nilai (field sensitif → lewat approval kyai).
        $scope = AiTenantSettingDefaults::konservatif()['scope_data'];
        $scope['nilai'] = true;
        $this->terapkanSetting(['scope_data' => $scope]);

        // Adapter dimock — fokus test ini gerbang scope, bukan seeding Akademik.
        $this->mock(AkademikToolAdapter::class)
            ->shouldReceive('getNilai')
            ->once()
            ->andReturn([['daftar' => [], 'jumlah' => 0, 'rata_rata' => null], 'Modules\Akademik — mock']);

        $jalan = $this->registry()->call('getNilai', ['santri_id' => 1], $admin);
        $this->assertTrue($jalan->sukses, (string) $jalan->pesan);
        $this->assertSame('Modules\Akademik — mock', $jalan->sumber);
    }

    public function test_ringkasan_keuangan_lewat_scope_dan_peran_bendahara(): void
    {
        $this->siapkanTenant('aitld');

        $scope = AiTenantSettingDefaults::konservatif()['scope_data'];
        $scope['keuangan'] = true;
        $this->terapkanSetting(['scope_data' => $scope]);

        $bendahara = $this->buatUser('bend@test.local', ['bendahara']);

        $this->mock(LedgerToolAdapter::class)
            ->shouldReceive('getRingkasanKeuangan')
            ->once()
            ->andReturn([[
                'neraca' => ['total_aset' => 1000000.0, 'seimbang' => true],
                'laba_rugi' => ['total_pendapatan' => 500000.0, 'total_beban' => 200000.0, 'surplus_defisit' => 300000.0],
                'arus_kas' => ['total_masuk' => 500000.0, 'total_keluar' => 200000.0, 'saldo_akhir' => 300000.0],
            ], 'Modules\Ledger — LaporanService (jurnal posted)']);

        $hasil = $this->registry()->call('getRingkasanKeuangan', [], $bendahara);

        $this->assertTrue($hasil->sukses, (string) $hasil->pesan);
        $this->assertStringContainsString('Ringkasan keuangan', $hasil->keTeks());

        // Peran wali TIDAK boleh mengintip ringkasan keuangan lembaga.
        $wali = $this->buatUser('wali-rk@test.local', ['wali']);
        $ditolak = $this->registry()->call('getRingkasanKeuangan', [], $wali);
        $this->assertFalse($ditolak->sukses);
        $this->assertStringContainsString('tidak memiliki akses', (string) $ditolak->pesan);
    }

    // ------------------------------------------------------------------
    // WALI: hanya anak tertaut (AccessWali)
    // ------------------------------------------------------------------

    public function test_wali_hanya_bisa_data_anak_tertaut(): void
    {
        $this->siapkanTenant('aitle');

        $wali = $this->buatUser('wali@test.local', ['wali']);
        $anak = $this->buatSantri('AIT010', 'Anak Sendiri');
        $anakOrang = $this->buatSantri('AIT011', 'Anak Orang Lain');

        AccessWali::create([
            'wali_id' => $wali->id,
            'santri_id' => $anak->id,
            'relationship_type' => 'ayah',
            'is_primary' => true,
        ]);

        $this->buatTagihan($anak, 150000);
        $this->buatTagihan($anakOrang, 999000);

        // Anak tertaut → boleh.
        $milik = $this->registry()->call('getTagihan', ['santri_id' => $anak->id], $wali);
        $this->assertTrue($milik->sukses, (string) $milik->pesan);
        $this->assertEqualsWithDelta(150000.0, $milik->nilai['total_sisa'], 0.01);

        // santri_id orang lain → ditolak SEBELUM adapter.
        $bukan = $this->registry()->call('getTagihan', ['santri_id' => $anakOrang->id], $wali);
        $this->assertFalse($bukan->sukses);
        $this->assertStringContainsString('tertaut', (string) $bukan->pesan);

        // getSantri (khusus wali): anak sendiri boleh, anak orang ditolak.
        $profil = $this->registry()->call('getSantri', ['santri_id' => $anak->id], $wali);
        $this->assertTrue($profil->sukses, (string) $profil->pesan);
        $this->assertSame('Anak Sendiri', $profil->nilai['nama']);

        $intip = $this->registry()->call('getSantri', ['santri_id' => $anakOrang->id], $wali);
        $this->assertFalse($intip->sukses);

        // Wali TANPA tautan sama sekali → ditolak.
        $waliKosong = $this->buatUser('wali2@test.local', ['wali']);
        $kosong = $this->registry()->call('getTagihan', ['santri_id' => $anak->id], $waliKosong);
        $this->assertFalse($kosong->sukses);
    }

    // ------------------------------------------------------------------
    // Isolasi tenant
    // ------------------------------------------------------------------

    public function test_isolasi_tenant_data_a_tak_terbaca_dari_b(): void
    {
        $this->siapkanTenant('aitlf');
        $santriA = $this->buatSantri('AIT020');
        $this->buatTagihan($santriA, 750000);
        tenancy()->end();

        $this->siapkanTenant('aitlg');
        $adminB = $this->buatUser('staf-b@test.local', ['admin_pesantren']);

        $hasil = $this->registry()->call('getTagihan', ['santri_id' => $santriA->id], $adminB);

        $this->assertTrue($hasil->sukses); // query sah, hasil HARUS kosong
        $this->assertSame(0, $hasil->nilai['jumlah']);
        $this->assertEqualsWithDelta(0.0, $hasil->nilai['total_sisa'], 0.01);
        $this->assertStringNotContainsString('750', json_encode($hasil->nilai));
    }

    // ------------------------------------------------------------------
    // Validasi args, tool tak dikenal, RBAC, tanpa identitas
    // ------------------------------------------------------------------

    public function test_args_invalid_dan_tool_tak_dikenal_gagal_rapi(): void
    {
        $this->siapkanTenant('aitlh');
        $admin = $this->buatUser('staf@test.local', ['admin_pesantren']);

        // Parameter wajib hilang.
        $tanpaArg = $this->registry()->call('getTagihan', [], $admin);
        $this->assertFalse($tanpaArg->sukses);
        $this->assertStringContainsString('santri_id', (string) $tanpaArg->pesan);

        // Tipe salah.
        $tipeSalah = $this->registry()->call('getTagihan', ['santri_id' => 'abc'], $admin);
        $this->assertFalse($tipeSalah->sukses);

        // Parameter asing ditolak (fail-closed).
        $asing = $this->registry()->call('getTagihan', ['santri_id' => 1, 'limit' => 99], $admin);
        $this->assertFalse($asing->sukses);
        $this->assertStringContainsString('limit', (string) $asing->pesan);

        // Tool tak terdaftar.
        $takDikenal = $this->registry()->call('hapusSemuaData', [], $admin);
        $this->assertFalse($takDikenal->sukses);
        $this->assertStringContainsString('tidak dikenali', (string) $takDikenal->pesan);
    }

    public function test_peran_tak_diizinkan_dan_tanpa_identitas_ditolak(): void
    {
        $this->siapkanTenant('aitli');

        $santriUser = $this->buatUser('santri@test.local', ['santri']);
        $rbac = $this->registry()->call('getTagihan', ['santri_id' => 1], $santriUser);
        $this->assertFalse($rbac->sukses);
        $this->assertStringContainsString('tidak memiliki akses', (string) $rbac->pesan);

        $anonim = $this->registry()->call('getTagihan', ['santri_id' => 1], null);
        $this->assertFalse($anonim->sukses);
        $this->assertStringContainsString('identitas', (string) $anonim->pesan);
    }

    // ------------------------------------------------------------------
    // getStatusPPDB
    // ------------------------------------------------------------------

    public function test_get_status_ppdb_staf_dan_pembatasan_peserta(): void
    {
        $this->siapkanTenant('aitlj');
        $admin = $this->buatUser('staf@test.local', ['admin_pesantren']);
        $pemilik = $this->buatUser('peserta1@test.local', ['peserta']);
        $orangLain = $this->buatUser('peserta2@test.local', ['peserta']);

        CalonSantri::create([
            'nomor_pendaftaran' => 'REG-AIT-001',
            'nama' => 'Calon Uji',
            'jenis_kelamin' => 'L',
            'tanggal_lahir' => '2012-01-01',
            'status_pendaftaran' => 'diterima',
            'tahun_ajaran' => '2026',
            'gelombang' => 'Gelombang 1',
            'user_id' => $pemilik->id,
        ]);

        // Staf boleh melihat siapa pun.
        $hasil = $this->registry()->call('getStatusPPDB', ['nomor_pendaftaran' => 'REG-AIT-001'], $admin);
        $this->assertTrue($hasil->sukses, (string) $hasil->pesan);
        $this->assertSame('diterima', $hasil->nilai['status_pendaftaran']);
        $this->assertStringContainsString('diterima', $hasil->keTeks());

        // Pemilik pendaftaran boleh.
        $sendiri = $this->registry()->call('getStatusPPDB', ['nomor_pendaftaran' => 'REG-AIT-001'], $pemilik);
        $this->assertTrue($sendiri->sukses, (string) $sendiri->pesan);

        // Peserta lain → "tidak ditemukan" (tanpa membocorkan keberadaan data).
        $intip = $this->registry()->call('getStatusPPDB', ['nomor_pendaftaran' => 'REG-AIT-001'], $orangLain);
        $this->assertFalse($intip->sukses);
        $this->assertStringContainsString('tidak ditemukan', (string) $intip->pesan);
    }

    // ------------------------------------------------------------------
    // READ-ONLY mutlak
    // ------------------------------------------------------------------

    public function test_adapter_read_only_tidak_ada_statement_tulis(): void
    {
        $this->siapkanTenant('aitlk');
        $admin = $this->buatUser('staf@test.local', ['admin_pesantren']);

        $santri = $this->buatSantri('AIT030');
        $this->buatTagihan($santri, 120000);

        $jumlahTagihan = Tagihan::query()->count();
        $jumlahSantri = Santri::query()->count();

        $tulis = [];
        DB::listen(function ($query) use (&$tulis): void {
            if (preg_match('/^\s*(insert|update|delete|truncate|alter|drop)\b/i', $query->sql)) {
                $tulis[] = $query->sql;
            }
        });

        $this->registry()->call('getTagihan', ['santri_id' => $santri->id], $admin);
        $this->registry()->call('getStatusBayar', ['santri_id' => $santri->id], $admin);
        $this->registry()->call('getJadwal', ['kelas_id' => 1], $admin);

        $this->assertSame([], $tulis, 'Tool data WAJIB read-only — terdeteksi statement tulis.');
        $this->assertSame($jumlahTagihan, Tagihan::query()->count());
        $this->assertSame($jumlahSantri, Santri::query()->count());
    }
}
