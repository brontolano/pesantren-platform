<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\KebijakanService;
use App\Support\KebijakanRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Kesantrian\Services\PesanWaliService;
use Modules\Kesantrian\Services\PresensiAsramaService;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-34 — Kebijakan Pesantren: registry, simpan/reset + efek nyata ke service,
 * ekspor-impor roundtrip, preset, dan audit trail.
 */
class KebijakanPesantrenTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private KebijakanService $svc;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurePpdbTestingDatabase();
        if (! Schema::connection('pgsql')->hasTable('settings') || ! Schema::connection('pgsql')->hasTable('audit_logs')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(): void
    {
        $tenant = $this->createRuntimeTenant('wp34', 'Pesantren WP34');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        (new RolesAndPermissionsSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::withoutGlobalScopes()->create([
            'name' => 'Admin Kebijakan',
            'email' => 'admin@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);

        $this->svc = app(KebijakanService::class);
    }

    public function test_registry_lengkap_dan_konsisten(): void
    {
        $entries = KebijakanRegistry::entries();

        $this->assertGreaterThanOrEqual(25, count($entries), 'Registry minimal mencakup kebijakan G1-G4 yang sudah dibuka.');

        foreach ($entries as $key => $entry) {
            $this->assertIsString($key);
            $this->assertNotSame('', $entry['label'], "Label kosong: {$key}");
            $this->assertNotSame('', $entry['deskripsi'], "Deskripsi kosong: {$key}");
            $this->assertArrayHasKey($entry['kelompok'], KebijakanRegistry::KELOMPOK, "Kelompok tak dikenal: {$key}");
            $this->assertContains($entry['tipe'], ['bool', 'number', 'select', 'json', 'text'], "Tipe tak dikenal: {$key}");
            if ($entry['tipe'] === 'select') {
                $this->assertNotEmpty($entry['options'] ?? [], "Select tanpa options: {$key}");
                $this->assertArrayHasKey($entry['default'], $entry['options'], "Default di luar options: {$key}");
            }
        }

        // Preset hanya memakai key terdaftar.
        foreach (KebijakanRegistry::presets() as $nama => $preset) {
            foreach (array_keys($preset['nilai']) as $key) {
                $this->assertArrayHasKey($key, $entries, "Preset {$nama} memakai key tak terdaftar: {$key}");
            }
        }
    }

    public function test_ubah_nilai_mengubah_setting_dan_perilaku_service(): void
    {
        $this->boot();

        // Default: moderasi pesan perhatian ON.
        $pesanWali = app(PesanWaliService::class);
        $this->assertTrue($pesanWali->moderasiPerhatianAktif());

        // Ubah lewat KebijakanService → perilaku service NYATA berubah.
        $this->svc->set('kesantrian.moderasi_pesan_perhatian', false, $this->admin);

        $this->assertFalse(Setting::getTenantValue('kesantrian.moderasi_pesan_perhatian'));
        $this->assertFalse($pesanWali->moderasiPerhatianAktif());

        // Contoh kedua: slot presensi asrama mengikuti kebijakan baru.
        $slotBaru = [['kode' => 'subuh', 'label' => 'Subuh Saja', 'jam_buka' => '04:00', 'jam_tutup' => '06:00']];
        $this->svc->set('kepengasuhan.slot_presensi', $slotBaru, $this->admin);
        $this->assertSame(['subuh'], app(PresensiAsramaService::class)->kodeSlotAktif());

        // disiplin.wajib_verifikasi: nilai efektif berubah dari default true → false.
        $this->svc->set('disiplin.wajib_verifikasi', false, $this->admin);
        $this->assertFalse($this->svc->nilai('disiplin.wajib_verifikasi'));

        tenancy()->end();
    }

    public function test_reset_kembali_ke_default(): void
    {
        $this->boot();

        $this->svc->set('kesantrian.maks_izin_bulan', 5, $this->admin);
        $this->assertSame(5, $this->svc->nilai('kesantrian.maks_izin_bulan'));
        $this->assertTrue($this->svc->adaOverride('kesantrian.maks_izin_bulan'));

        $this->svc->reset('kesantrian.maks_izin_bulan', $this->admin);

        $this->assertFalse($this->svc->adaOverride('kesantrian.maks_izin_bulan'));
        $this->assertSame(2, $this->svc->nilai('kesantrian.maks_izin_bulan'), 'Setelah reset harus kembali ke default registry.');

        tenancy()->end();
    }

    public function test_ekspor_impor_roundtrip_utuh(): void
    {
        $this->boot();

        $this->svc->set('kesantrian.maks_izin_bulan', 4, $this->admin);
        $this->svc->set('disiplin.notif_wali', 'semua', $this->admin);
        $this->svc->set('akademik.hari_libur_mingguan', ['jumat'], $this->admin);

        $ekspor = $this->svc->ekspor();
        $json = json_encode($ekspor);

        // Hapus semua override (simulasi tenant lain / restore).
        Setting::query()->where('tenant_id', tenant('id'))->delete();
        $this->assertSame(2, $this->svc->nilai('kesantrian.maks_izin_bulan'));

        $payload = json_decode($json, true);
        $pratinjau = $this->svc->pratinjauImpor($payload);
        $this->assertCount(3, $pratinjau['diff']);
        $this->assertSame([], $pratinjau['diabaikan']);

        $this->svc->terapkanImpor($payload, $this->admin);

        $this->assertSame(4, $this->svc->nilai('kesantrian.maks_izin_bulan'));
        $this->assertSame('semua', $this->svc->nilai('disiplin.notif_wali'));
        $this->assertSame(['jumat'], $this->svc->nilai('akademik.hari_libur_mingguan'));

        // Key liar diabaikan, tidak meledak dan tidak tersimpan.
        $liar = ['versi' => 1, 'kebijakan' => ['kunci.liar' => true]];
        $hasil = $this->svc->pratinjauImpor($liar);
        $this->assertSame(['kunci.liar'], $hasil['diabaikan']);
        $this->assertSame(0, $this->svc->terapkanImpor($liar, $this->admin));

        tenancy()->end();
    }

    public function test_preset_diterapkan_dan_diaudit(): void
    {
        $this->boot();

        $jumlah = $this->svc->terapkanPreset('salafiyah-kecil', $this->admin);

        $this->assertGreaterThan(0, $jumlah);
        $this->assertSame(['jumat'], $this->svc->nilai('akademik.hari_libur_mingguan'));
        $this->assertSame(1, $this->svc->nilai('kesantrian.maks_izin_bulan'));
        $this->assertFalse($this->svc->nilai('disiplin.wajib_verifikasi'));

        $this->assertTrue(
            AuditLog::query()->where('tenant_id', tenant('id'))
                ->where('action', 'kebijakan.preset_diterapkan')->exists(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->svc->terapkanPreset('tidak-ada', $this->admin);
    }

    public function test_set_menolak_nilai_bool_tidak_valid(): void
    {
        $this->boot();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('disiplin.wajib_verifikasi');
        $this->svc->set('disiplin.wajib_verifikasi', 'banana', $this->admin);
    }

    public function test_set_menolak_number_di_luar_rentang_dan_non_angka(): void
    {
        $this->boot();

        // Non-angka ditolak.
        try {
            $this->svc->set('kesantrian.maks_izin_bulan', 'banyak', $this->admin);
            $this->fail('Nilai non-angka harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('harus angka', $e->getMessage());
        }

        // Di atas max ditolak.
        try {
            $this->svc->set('kesantrian.maks_izin_bulan', 99, $this->admin);
            $this->fail('Nilai melebihi max harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('maksimal 31', $e->getMessage());
        }

        // Di bawah min ditolak.
        try {
            $this->svc->set('payroll.thr_nominal', -1, $this->admin);
            $this->fail('Nilai di bawah min harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('minimal 0', $e->getMessage());
        }

        // Select di luar options ditolak.
        try {
            $this->svc->set('disiplin.notif_wali', 'kadang-kadang', $this->admin);
            $this->fail('Nilai select di luar options harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('salah satu dari', $e->getMessage());
        }

        // JSON tak valid ditolak.
        try {
            $this->svc->set('akademik.hari_libur_mingguan', '{rusak', $this->admin);
            $this->fail('JSON rusak harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('JSON valid', $e->getMessage());
        }

        // Tidak ada override yang ikut tersimpan dari percobaan gagal.
        $this->assertFalse($this->svc->adaOverride('kesantrian.maks_izin_bulan'));
        $this->assertFalse($this->svc->adaOverride('payroll.thr_nominal'));

        // Nilai valid pada batas tetap diterima.
        $this->svc->set('kesantrian.maks_izin_bulan', 31, $this->admin);
        $this->assertSame(31, $this->svc->nilai('kesantrian.maks_izin_bulan'));

        tenancy()->end();
    }

    public function test_impor_nilai_tidak_valid_ditolak_per_key_nilai_valid_tetap_diterapkan(): void
    {
        $this->boot();

        $payload = [
            'versi' => 1,
            'kebijakan' => [
                'kesantrian.maks_izin_bulan' => 4,           // valid
                'disiplin.wajib_verifikasi' => 'banana',     // bool tak valid
                'kesantrian.approval_h_minimum' => 999,      // di luar rentang
                'disiplin.notif_wali' => 'semua',            // valid
            ],
        ];

        $pratinjau = $this->svc->pratinjauImpor($payload);

        $ditolakKeys = array_column($pratinjau['ditolak'], 'key');
        $this->assertEqualsCanonicalizing(
            ['disiplin.wajib_verifikasi', 'kesantrian.approval_h_minimum'],
            $ditolakKeys,
        );
        foreach ($pratinjau['ditolak'] as $row) {
            $this->assertNotSame('', $row['alasan']);
        }
        $this->assertEqualsCanonicalizing(
            ['kesantrian.maks_izin_bulan', 'disiplin.notif_wali'],
            array_column($pratinjau['diff'], 'key'),
        );

        // Terapkan: hanya key valid tersimpan, key ditolak dilewati tanpa meledak.
        $this->assertSame(2, $this->svc->terapkanImpor($payload, $this->admin));
        $this->assertSame(4, $this->svc->nilai('kesantrian.maks_izin_bulan'));
        $this->assertSame('semua', $this->svc->nilai('disiplin.notif_wali'));
        $this->assertTrue($this->svc->nilai('disiplin.wajib_verifikasi'), 'Nilai ditolak tidak boleh tersimpan.');
        $this->assertSame(3, $this->svc->nilai('kesantrian.approval_h_minimum'), 'Nilai di luar rentang tidak boleh tersimpan.');

        tenancy()->end();
    }

    public function test_audit_mencatat_key_dari_ke_dan_user(): void
    {
        $this->boot();

        $this->svc->set('keuangan.dscr.ambang', 1.5, $this->admin);

        $log = AuditLog::query()
            ->where('tenant_id', tenant('id'))
            ->where('action', 'kebijakan.diubah')
            ->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame((string) $this->admin->id, (string) $log->user_id);
        $this->assertSame('keuangan.dscr.ambang', $log->metadata['key']);
        $this->assertEquals(1.25, $log->metadata['dari']);
        $this->assertEquals(1.5, $log->metadata['ke']);

        // Set ulang nilai sama → TIDAK menambah audit (idempoten).
        $count = AuditLog::query()->where('tenant_id', tenant('id'))->count();
        $this->svc->set('keuangan.dscr.ambang', 1.5, $this->admin);
        $this->assertSame($count, AuditLog::query()->where('tenant_id', tenant('id'))->count());

        tenancy()->end();
    }
}
