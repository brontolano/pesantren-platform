<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\PesanWali;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\PesanWaliService;
use Modules\Notifikasi\Models\LogNotifikasi;
use Modules\Payroll\Models\SlipGaji;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F3-PWA — Pesan Wali + Slip Saya: otorisasi binaan (403 di luar
 * kelas/halaqah), saklar moderasi 'perhatian' policy-first (2 tenant beda),
 * approve/tolak panel → delivery, client_ref idempoten SCOPED guru+santri
 * (ref orang lain → 409), slip gaji hanya milik sendiri (lihat + print).
 */
class PwaGuruF3Test extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'model_has_roles', 'model_has_permissions', 'settings',
            'santri', 'kelas', 'pelajaran', 'jadwal_pelajaran', 'periode_akademik',
            'pesan_wali', 'log_notifikasi', 'template_notifikasi', 'slip_gaji',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // (a) Otorisasi binaan: santri luar kelas/halaqah → 403
    // ---------------------------------------------------------------

    public function test_guru_tak_bisa_kirim_ke_santri_di_luar_binaan(): void
    {
        $tenant = $this->bootTenant('pwf3a');

        $guru = $this->createGuru('guru-binaan@test.local');
        $guru->givePermissionTo('akses_pwa_guru');
        $kelasKu = $this->createKelasDenganJadwal($guru, 'Fiqih');
        $binaan = $this->createSantri('BIN001', $kelasKu->id);

        $kelasLain = Kelas::create(['nama_kelas' => 'IX-LAIN', 'tingkat' => '9', 'capacity' => 30, 'is_active' => true]);
        $luar = $this->createSantri('LUAR01', $kelasLain->id);

        Auth::login($guru);

        // Santri kelas lain → 403, TIDAK ada baris tertulis.
        $status = $this->hit($tenant, 'POST', '/guru/pesan', $this->payloadPesan($luar->id))->getStatusCode();
        $this->assertSame(403, $status);
        $this->assertSame(0, PesanWali::count());

        // Santri binaan → sukses (apresiasi langsung terkirim).
        $this->assertSame(302, $this->hit($tenant, 'POST', '/guru/pesan', $this->payloadPesan($binaan->id))->getStatusCode());
        $this->assertSame(1, PesanWali::count());
        $this->assertSame('terkirim', PesanWali::first()->status);

        // Index hanya menampilkan binaan, bukan santri kelas lain.
        $index = $this->hit($tenant, 'GET', '/guru/pesan');
        $this->assertSame(200, $index->getStatusCode());
        $this->assertStringContainsString('Santri BIN001', $index->getContent());
        $this->assertStringNotContainsString('Santri LUAR01', $index->getContent());
        $this->assertStringContainsString('PortalGuru\/PesanWali', $index->getContent());
    }

    // ---------------------------------------------------------------
    // (b) Saklar moderasi policy-first: 2 tenant beda kebijakan
    // ---------------------------------------------------------------

    public function test_moderasi_on_perhatian_tertahan_off_langsung_apresiasi_selalu_langsung(): void
    {
        // Tenant A — default (moderasi ON).
        $tenantA = $this->bootTenant('pwf3m');
        $guruA = $this->createGuru('guru-mod-a@test.local');
        $guruA->givePermissionTo('akses_pwa_guru');
        $kelasA = $this->createKelasDenganJadwal($guruA, 'Nahwu');
        $santriA = $this->createSantri('MODA01', $kelasA->id);

        Auth::login($guruA);

        // 'perhatian' → tertahan menunggu_moderasi, TANPA notifikasi.
        $this->hit($tenantA, 'POST', '/guru/pesan', $this->payloadPesan($santriA->id, 'perhatian'));
        $perhatian = PesanWali::where('jenis', 'perhatian')->firstOrFail();
        $this->assertSame('menunggu_moderasi', $perhatian->status);
        $this->assertNull($perhatian->sent_at);
        $this->assertSame(0, LogNotifikasi::where('jenis', 'pesan_wali')->count(), 'Pesan tertahan TIDAK boleh terkirim ke portal');

        // 'apresiasi' selalu langsung terkirim meski moderasi ON.
        $this->hit($tenantA, 'POST', '/guru/pesan', $this->payloadPesan($santriA->id, 'apresiasi'));
        $this->assertSame('terkirim', PesanWali::where('jenis', 'apresiasi')->first()->status);

        tenancy()->end();

        // Tenant B — saklar OFF: 'perhatian' langsung terkirim.
        $tenantB = $this->bootTenant('pwf3n');
        Setting::create([
            'tenant_id' => $tenantB->id,
            'key' => PesanWaliService::SETTING_MODERASI,
            'value' => ['value' => false],
        ]);

        $guruB = $this->createGuru('guru-mod-b@test.local');
        $guruB->givePermissionTo('akses_pwa_guru');
        $kelasB = $this->createKelasDenganJadwal($guruB, 'Shorof');
        $santriB = $this->createSantri('MODB01', $kelasB->id);

        Auth::login($guruB);

        $this->hit($tenantB, 'POST', '/guru/pesan', $this->payloadPesan($santriB->id, 'perhatian'));
        $pesanB = PesanWali::where('jenis', 'perhatian')->firstOrFail();
        $this->assertSame('terkirim', $pesanB->status, 'Saklar OFF: perhatian langsung terkirim');
        $this->assertNotNull($pesanB->sent_at);
    }

    // ---------------------------------------------------------------
    // (c) Moderasi panel: setujui → terkirim + notif; tolak → ditolak + alasan
    // ---------------------------------------------------------------

    public function test_setujui_mengirim_notifikasi_dan_tolak_menyimpan_alasan(): void
    {
        $tenant = $this->bootTenant('pwf3p');

        $wali = User::create(['name' => 'Wali Uji', 'email' => 'wali-f3@test.local', 'password' => 'password']);
        $guru = $this->createGuru('guru-panel@test.local');
        $guru->givePermissionTo('akses_pwa_guru');
        $kelas = $this->createKelasDenganJadwal($guru, 'Tarikh');
        $santri = $this->createSantri('PNL001', $kelas->id);
        $santri->update(['wali_user_id' => $wali->id, 'no_hp_wali' => '081234567890']);

        Auth::login($guru);
        $payload1 = $this->payloadPesan($santri->id, 'perhatian');
        $this->hit($tenant, 'POST', '/guru/pesan', $payload1);
        $this->hit($tenant, 'POST', '/guru/pesan', $payload1);
        $this->assertSame(1, PesanWali::count(), 'client_ref sama dipakai dua kali tidak menggandakan');

        // Pesan kedua (client_ref baru) untuk skenario tolak.
        $this->hit($tenant, 'POST', '/guru/pesan', $this->payloadPesan($santri->id, 'perhatian'));
        $this->assertSame(2, PesanWali::count());

        [$pesan1, $pesan2] = PesanWali::orderBy('id')->get()->all();
        $moderator = $this->createGuru('moderator@test.local');
        $service = app(PesanWaliService::class);

        // Setujui → terkirim + moderated_by + notifikasi portal ke email wali.
        $service->setujui($pesan1, $moderator->id);
        $pesan1->refresh();
        $this->assertSame('terkirim', $pesan1->status);
        $this->assertSame($moderator->id, $pesan1->moderated_by);
        $this->assertNotNull($pesan1->sent_at);

        $notif = LogNotifikasi::where('jenis', 'pesan_wali')->get();
        $this->assertCount(1, $notif, 'Setujui harus mendispatch notifikasi portal wali');
        $this->assertSame('wali-f3@test.local', $notif->first()->target);
        $this->assertSame('database', $notif->first()->channel);
        $this->assertSame('Isi pesan percobaan untuk wali.', $notif->first()->payload['pesan']);

        // Tolak → ditolak + alasan, TANPA notifikasi tambahan.
        $service->tolak($pesan2, $moderator->id, 'Bahasa kurang tepat, mohon perhalus.');
        $pesan2->refresh();
        $this->assertSame('ditolak', $pesan2->status);
        $this->assertSame('Bahasa kurang tepat, mohon perhalus.', $pesan2->alasan_tolak);
        $this->assertSame(1, LogNotifikasi::where('jenis', 'pesan_wali')->count(), 'Tolak tidak boleh mengirim ke wali');

        // Idempoten: setujui pesan yang sudah ditolak tidak mengubah apa pun.
        $service->setujui($pesan2, $moderator->id);
        $this->assertSame('ditolak', $pesan2->fresh()->status);
    }

    // ---------------------------------------------------------------
    // (d) client_ref idempoten SCOPED guru+santri — ref orang lain → 409
    // ---------------------------------------------------------------

    public function test_client_ref_scoped_guru_dan_santri_ref_orang_lain_409(): void
    {
        $tenant = $this->bootTenant('pwf3r');

        $guruA = $this->createGuru('guru-ref-a@test.local');
        $guruA->givePermissionTo('akses_pwa_guru');
        $kelasA = $this->createKelasDenganJadwal($guruA, 'Balaghah');
        $santriA = $this->createSantri('REF001', $kelasA->id);

        $guruB = $this->createGuru('guru-ref-b@test.local');
        $guruB->givePermissionTo('akses_pwa_guru');
        $kelasB = $this->createKelasDenganJadwal($guruB, 'Mantiq');
        $santriB = $this->createSantri('REF002', $kelasB->id);

        $ref = (string) Str::uuid();

        // Guru A kirim + retry persis sama → 1 baris.
        Auth::login($guruA);
        $payloadA = $this->payloadPesan($santriA->id, 'apresiasi', $ref);
        $this->assertSame(302, $this->hit($tenant, 'POST', '/guru/pesan', $payloadA)->getStatusCode());
        $this->assertSame(302, $this->hit($tenant, 'POST', '/guru/pesan', $payloadA)->getStatusCode());
        $this->assertSame(1, PesanWali::count(), 'Retry client_ref sama = 1 pesan');

        // Guru B memakai client_ref guru A (santri lain) → 409, tidak menulis.
        Auth::login($guruB);
        $status = $this->hit($tenant, 'POST', '/guru/pesan', $this->payloadPesan($santriB->id, 'apresiasi', $ref))->getStatusCode();
        $this->assertSame(409, $status, 'client_ref orang lain harus 409');
        $this->assertSame(1, PesanWali::count());

        // Guru A memakai ref yang sama untuk SANTRI BERBEDA → juga 409
        // (dedup scoped guru+santri, bukan guru saja).
        $santriA2 = $this->createSantri('REF003', $kelasA->id);
        Auth::login($guruA);
        $status = $this->hit($tenant, 'POST', '/guru/pesan', $this->payloadPesan($santriA2->id, 'apresiasi', $ref))->getStatusCode();
        $this->assertSame(409, $status);
        $this->assertSame(1, PesanWali::count());
    }

    // ---------------------------------------------------------------
    // (e) Slip Saya: hanya milik sendiri; print slip orang lain → 403
    // ---------------------------------------------------------------

    public function test_slip_saya_hanya_milik_sendiri_dan_print_orang_lain_403(): void
    {
        $tenant = $this->bootTenant('pwf3s');

        $guruA = $this->createGuru('guru-slip-a@test.local');
        $guruA->assignRole('guru'); // akses_pwa_guru + view_slip_sendiri dari seeder
        $guruB = $this->createGuru('guru-slip-b@test.local');
        $guruB->assignRole('guru');

        $slipA = SlipGaji::create([
            'users_id' => $guruA->id, 'periode' => '2026-05', 'status' => 'final',
            'bruto' => 3000000, 'pph21_dipotong' => 0, 'neto' => 3000000,
        ]);
        $slipADraft = SlipGaji::create([
            'users_id' => $guruA->id, 'periode' => '2026-06', 'status' => 'draft',
            'bruto' => 3100000, 'pph21_dipotong' => 0, 'neto' => 3100000,
        ]);
        $slipB = SlipGaji::create([
            'users_id' => $guruB->id, 'periode' => '2026-05', 'status' => 'final',
            'bruto' => 9000000, 'pph21_dipotong' => 0, 'neto' => 9000000,
        ]);

        Auth::login($guruA->fresh());

        // List hanya milik A (final + draft ber-badge), nominal B tidak bocor.
        $list = $this->hit($tenant, 'GET', '/guru/slip-saya');
        $this->assertSame(200, $list->getStatusCode());
        $content = $list->getContent();
        $this->assertStringContainsString('PortalGuru\/SlipSaya', $content);
        $this->assertStringContainsString('2026-05', $content);
        $this->assertStringContainsString('"status":"draft"', $content);
        $this->assertStringNotContainsString('9000000', $content, 'Slip guru B tidak boleh bocor ke guru A');

        // Print milik sendiri (final) → 200; milik guru B → 403.
        $this->assertSame(200, $this->hit($tenant, 'GET', "/admin/payroll/slip/{$slipA->id}/print")->getStatusCode());
        $this->assertSame(403, $this->hit($tenant, 'GET', "/admin/payroll/slip/{$slipB->id}/print")->getStatusCode());

        // Guru B juga hanya melihat miliknya.
        Auth::login($guruB->fresh());
        $listB = $this->hit($tenant, 'GET', '/guru/slip-saya')->getContent();
        $this->assertStringContainsString('9000000', $listB);
        $this->assertStringNotContainsString('3000000', $listB);

        // Tanpa permission view_slip_sendiri → print 403 bahkan milik sendiri.
        $polos = $this->createGuru('guru-slip-polos@test.local');
        $polos->givePermissionTo('akses_pwa_guru');
        $slipPolos = SlipGaji::create([
            'users_id' => $polos->id, 'periode' => '2026-05', 'status' => 'final',
            'bruto' => 100, 'pph21_dipotong' => 0, 'neto' => 100,
        ]);
        Auth::login($polos);
        $this->assertSame(403, $this->hit($tenant, 'GET', "/admin/payroll/slip/{$slipPolos->id}/print")->getStatusCode());
        // Adversarial F3: angka gaji sensitif — tanpa view_slip_sendiri,
        // LIST pun 403 (bukan cuma tombol cetak yang hilang).
        $this->assertSame(403, $this->hit($tenant, 'GET', '/guru/slip-saya')->getStatusCode(), 'Tanpa view_slip_sendiri list slip ditolak');

        $this->assertNotNull($slipADraft);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function bootTenant(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        return $tenant;
    }

    private function hit(Tenant $tenant, string $method, string $uri, array $payload = [])
    {
        $host = $tenant->domains()->first()->domain;
        $request = Request::create("http://{$host}{$uri}", $method, $payload);
        $request->headers->set('Host', $host);

        $response = app(Kernel::class)->handle($request);

        if (! tenancy()->initialized) {
            tenancy()->initialize($tenant);
        }

        return $response;
    }

    private function createGuru(string $email): User
    {
        return User::create([
            'name' => 'Guru '.$email,
            'email' => $email,
            'password' => 'password',
        ]);
    }

    private function createSantri(string $nis, ?int $kelasId): Santri
    {
        return Santri::create([
            'nis' => $nis,
            'nama' => 'Santri '.$nis,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => $kelasId,
        ]);
    }

    /** Kelas yang diampu guru lewat jadwal pelajaran (pola otorisasi F2). */
    private function createKelasDenganJadwal(User $guru, string $namaPelajaran): Kelas
    {
        $kelas = Kelas::create([
            'nama_kelas' => 'VII-'.strtoupper(substr(md5($namaPelajaran), 0, 3)),
            'tingkat' => '7',
            'capacity' => 30,
            'is_active' => true,
        ]);

        $periode = PeriodeAkademik::firstOrCreate(
            ['tahun_akademik' => '2026/2027', 'semester' => 'ganjil'],
            [
                'nama' => 'Semester Uji',
                'tanggal_mulai' => now()->subMonth()->toDateString(),
                'tanggal_akhir' => now()->addMonths(5)->toDateString(),
                'is_active' => true,
            ],
        );

        $pelajaran = Pelajaran::create([
            'nama' => $namaPelajaran,
            'kode' => strtoupper(substr(md5($namaPelajaran), 0, 6)),
            'sks' => 2,
            'guru_pengampu_id' => $guru->id,
        ]);

        JadwalPelajaran::create([
            'kelas_id' => $kelas->id,
            'pelajaran_id' => $pelajaran->id,
            'periode_akademik_id' => $periode->id,
            'hari' => 'senin',
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
        ]);

        return $kelas;
    }

    private function payloadPesan(int $santriId, string $jenis = 'apresiasi', ?string $clientRef = null): array
    {
        return [
            'santri_id' => $santriId,
            'jenis' => $jenis,
            'isi' => 'Isi pesan percobaan untuk wali.',
            'client_ref' => $clientRef ?? (string) Str::uuid(),
        ];
    }
}
