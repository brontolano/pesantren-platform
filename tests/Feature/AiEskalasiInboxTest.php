<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Livewire\Livewire;
use Modules\AI\Filament\Pages\EskalasiTanyaUstadz;
use Modules\AI\Models\AiApproval;
use Modules\AI\Models\AiEscalation;
use Modules\AI\Models\AiTenantSetting;
use Modules\AI\Services\Escalation\EscalationService;
use Modules\AI\Services\Escalation\LaporanEskalasi;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Modules\AI\Services\Settings\AiTenantSettingDefaults;
use Modules\AI\Services\Settings\TenantSettingService;
use Modules\Notifikasi\Models\LogNotifikasi;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A023 — antrean eskalasi "Tanya Ustadz" & laporan kyai:
 *  - tugaskan: tujuan default dari setting Wizard (entri pertama yang valid),
 *    SLA dari setting (fallback config), notifikasi penerima;
 *  - jawab: status + jejak penjawab + penerusan ke kanal asal (kanal WA =
 *    stub LogNotifikasi 'pending' menunggu T-A050 — TANPA jalur WA baru);
 *  - pengingat SLA terjadwal: SEKALI per tenggat (diingatkan_pada);
 *  - auto-assign eskalasi 'baru' yang menggantung;
 *  - laporan bulanan: agregat benar + contoh anonim terbatas & terredaksi;
 *  - RBAC halaman inbox (kelola/laporan) + isolasi antrean antar tenant.
 */
class AiEskalasiInboxTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ini_set('memory_limit', '512M');

        $this->ensurePpdbTestingSchemaReady([
            'ai_tenant_setting', 'ai_approval', 'ai_escalation', 'log_notifikasi',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        // Baseline gating modul: registry kosong = modul tampil (pola
        // ModuleService, sama dengan AiWizardPengaturanTest).
        DB::table('module_registry')->delete();

        Filament::setCurrentPanel(Filament::getPanel('tenant'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

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

    private function buatUser(string $email, array $permissions = [], array $roles = []): User
    {
        $user = User::create([
            'name' => 'Uji '.$email,
            'email' => $email,
            'password' => 'password',
        ]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        return $user;
    }

    /** Aktifkan setting dengan override (alur approval bila sensitif). */
    private function terapkanSetting(array $overrides): void
    {
        $admin = $this->buatUser('admin-'.uniqid().'@test.local', ['ai.setting.kelola']);
        $kyai = $this->buatUser('kyai-'.uniqid().'@test.local', ['ai.setting.setujui']);
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

    private function buatEskalasi(array $override = []): AiEscalation
    {
        static $urut = 0;
        $urut++;

        return AiEscalation::query()->create(array_merge([
            'conversation_ref' => 'percakapan-uji-'.$urut,
            'kanal' => 'web',
            'pertanyaan_ringkas' => "Pertanyaan uji ke-{$urut} dengan nomor [REDAKSI:hp].",
            'kategori' => 'level_fatwa',
            'status' => AiEscalation::STATUS_BARU,
        ], $override));
    }

    private function service(): EscalationService
    {
        return app(EscalationService::class);
    }

    // ------------------------------------------------------------------
    // Penugasan
    // ------------------------------------------------------------------

    public function test_tugaskan_memakai_tujuan_default_pertama_yang_valid_dengan_sla_setting(): void
    {
        Carbon::setTestNow('2026-06-15 08:00:00');
        $this->siapkanTenant('aiesk1');

        $ustadz = $this->buatUser('ustadz1@test.local', ['ai.eskalasi.kelola']);

        // Entri pertama (987654) tidak merujuk user mana pun — wajib dilewati.
        $this->terapkanSetting([
            'eskalasi_tujuan' => [
                'penerima' => [987654, $ustadz->id],
                'kanal' => 'panel',
                'sla_jam' => 12,
            ],
        ]);

        $eskalasi = $this->buatEskalasi();
        $hasil = $this->service()->tugaskan($eskalasi->id);

        $this->assertSame(AiEscalation::STATUS_DITUGASKAN, $hasil->status);
        $this->assertSame($ustadz->id, $hasil->ditugaskan_ke, 'Entri tak valid harus dilewati ke entri valid berikutnya');
        $this->assertSame(
            now()->addHours(12)->format('Y-m-d H:i:s'),
            $hasil->sla_deadline?->format('Y-m-d H:i:s'),
            'SLA harus mengikuti eskalasi_tujuan.sla_jam setting (12 jam), bukan default config',
        );
        $this->assertNull($hasil->diingatkan_pada);

        $this->assertSame(1, LogNotifikasi::query()
            ->where('jenis', 'ai_eskalasi_tugas')
            ->where('target', 'user:'.$ustadz->id)
            ->count());
    }

    public function test_tugaskan_tanpa_tujuan_default_valid_ditolak(): void
    {
        $this->siapkanTenant('aiesk2');
        // Setting default provisioning: eskalasi_tujuan.penerima = [] (kosong).
        $eskalasi = $this->buatEskalasi();

        $this->expectException(InvalidArgumentException::class);
        $this->service()->tugaskan($eskalasi->id);
    }

    // ------------------------------------------------------------------
    // Jawaban manusia + penerusan ke kanal asal
    // ------------------------------------------------------------------

    public function test_jawab_mencatat_jejak_dan_meneruskan_via_stub_kanal(): void
    {
        Carbon::setTestNow('2026-06-15 09:00:00');
        $this->siapkanTenant('aiesk3');

        $ustadz = $this->buatUser('ustadz3@test.local', ['ai.eskalasi.kelola']);

        // Kanal WA: jawaban tersimpan + stub antrean kirim-balik 'pending'
        // (menunggu kanal WA inbound T-A050) — TANPA jalur WA baru.
        $dariWa = $this->buatEskalasi(['kanal' => 'wa']);
        $hasil = $this->service()->jawab($dariWa->id, $ustadz, '  Jawaban santun dari ustadz.  ');

        $this->assertSame(AiEscalation::STATUS_DIJAWAB, $hasil->status);
        $this->assertSame('Jawaban santun dari ustadz.', $hasil->jawaban);
        $this->assertSame($ustadz->id, $hasil->dijawab_oleh);
        $this->assertNotNull($hasil->dijawab_pada);

        $stubWa = LogNotifikasi::query()
            ->where('jenis', 'ai_eskalasi_jawaban_kanal')
            ->where('target', 'kanal:wa')
            ->get();
        $this->assertCount(1, $stubWa);
        $this->assertSame('pending', $stubWa->first()->status, 'Kanal WA = pending menunggu T-A050');
        $this->assertSame('Jawaban santun dari ustadz.', $stubWa->first()->payload['jawaban']);
        $this->assertSame($dariWa->conversation_ref, $stubWa->first()->payload['conversation_ref']);

        $this->assertSame(1, LogNotifikasi::query()->where('jenis', 'ai_eskalasi_dijawab')->count());

        // Kanal panel/web: jawaban dianggap tersampaikan via panel ('sent').
        $dariWeb = $this->buatEskalasi(['kanal' => 'web']);
        $this->service()->jawab($dariWeb->id, $ustadz, 'Jawaban kedua.');

        $this->assertSame('sent', LogNotifikasi::query()
            ->where('jenis', 'ai_eskalasi_jawaban_kanal')
            ->where('target', 'kanal:web')
            ->value('status'));

        // Yang sudah dijawab tidak dapat dijawab ulang.
        $this->expectException(InvalidArgumentException::class);
        $this->service()->jawab($dariWa->id, $ustadz, 'Jawaban ulang.');
    }

    public function test_jawab_dan_tutup_butuh_izin_kelola(): void
    {
        $this->siapkanTenant('aiesk4');

        $polos = $this->buatUser('polos4@test.local');
        $eskalasi = $this->buatEskalasi();

        try {
            $this->service()->jawab($eskalasi->id, $polos, 'Jawaban tanpa izin.');
            $this->fail('jawab() tanpa izin ai.eskalasi.kelola harus ditolak.');
        } catch (AuthorizationException) {
            // diharapkan
        }

        $this->expectException(AuthorizationException::class);
        $this->service()->tutup($eskalasi->id, $polos);
    }

    public function test_tutup_menandai_waktu_dan_menolak_tutup_ulang(): void
    {
        $this->siapkanTenant('aiesk5');

        $ustadz = $this->buatUser('ustadz5@test.local', ['ai.eskalasi.kelola']);
        $eskalasi = $this->buatEskalasi();

        $this->service()->jawab($eskalasi->id, $ustadz, 'Jawaban sebelum ditutup.');
        $hasil = $this->service()->tutup($eskalasi->id, $ustadz);

        $this->assertSame(AiEscalation::STATUS_DITUTUP, $hasil->status);
        $this->assertNotNull($hasil->ditutup_pada);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->tutup($eskalasi->id, $ustadz);
    }

    // ------------------------------------------------------------------
    // Perintah terjadwal: pengingat SLA + auto-assign
    // ------------------------------------------------------------------

    public function test_pengingat_sla_terjadwal_sekali_dan_auto_assign_baru(): void
    {
        Carbon::setTestNow('2026-06-15 06:00:00');
        $tenant = $this->siapkanTenant('aiesk6');

        $ustadz = $this->buatUser('ustadz6@test.local', ['ai.eskalasi.kelola']);
        $this->terapkanSetting([
            'eskalasi_tujuan' => [
                'penerima' => [$ustadz->id],
                'kanal' => 'panel',
                'sla_jam' => 12,
            ],
        ]);

        // Sudah ditugaskan jam 06:00 → tenggat 18:00.
        $ditugaskan = $this->buatEskalasi();
        $this->service()->tugaskan($ditugaskan->id);

        // Masih 'baru' sejak 06:00 → kandidat auto-assign (> 30 menit).
        $menggantung = $this->buatEskalasi();

        // 13 jam kemudian: tenggat terlampaui & antrean menggantung lama.
        Carbon::setTestNow('2026-06-15 19:00:00');
        tenancy()->end();

        $this->artisan('ai:eskalasi-pengingat', ['tenant' => $tenant->id])->assertExitCode(0);

        tenancy()->initialize($tenant);

        $ditugaskan->refresh();
        $this->assertNotNull($ditugaskan->diingatkan_pada, 'Pengingat SLA harus menandai diingatkan_pada');
        $this->assertSame(1, LogNotifikasi::query()
            ->where('jenis', 'ai_eskalasi_pengingat')
            ->where('target', 'user:'.$ustadz->id)
            ->count());

        $menggantung->refresh();
        $this->assertSame(AiEscalation::STATUS_DITUGASKAN, $menggantung->status, 'Eskalasi baru menggantung harus auto-assign');
        $this->assertSame($ustadz->id, $menggantung->ditugaskan_ke);

        // Jalan ulang: pengingat TIDAK dobel (sudah ditandai); yang baru
        // ditugaskan belum melewati tenggat barunya.
        tenancy()->end();
        $this->artisan('ai:eskalasi-pengingat', ['tenant' => $tenant->id])->assertExitCode(0);
        tenancy()->initialize($tenant);

        $this->assertSame(1, LogNotifikasi::query()
            ->where('jenis', 'ai_eskalasi_pengingat')
            ->count(), 'Pengingat SLA hanya boleh terkirim SEKALI per tenggat');
    }

    // ------------------------------------------------------------------
    // Laporan kyai
    // ------------------------------------------------------------------

    public function test_laporan_bulanan_agregat_benar_dengan_contoh_anonim_terbatas(): void
    {
        $this->siapkanTenant('aiesk7');

        // Bulan LALU (Mei) — wajib tereksklusi dari laporan Juni.
        Carbon::setTestNow('2026-05-20 10:00:00');
        $this->buatEskalasi(['kategori' => 'blacklist_topik']);

        // Bulan berjalan (Juni): 7 eskalasi — 4 level_fatwa, 3 blacklist_topik.
        Carbon::setTestNow('2026-06-10 08:00:00');
        $bulanIni = collect(range(1, 7))->map(fn (int $i): AiEscalation => $this->buatEskalasi([
            'kategori' => $i <= 4 ? 'level_fatwa' : 'blacklist_topik',
        ]));

        // Dua dijawab: 2 jam dan 4 jam sejak masuk → rata-rata 3,0 jam.
        $bulanIni[0]->update([
            'status' => AiEscalation::STATUS_DIJAWAB,
            'dijawab_pada' => Carbon::parse('2026-06-10 10:00:00'),
        ]);
        $bulanIni[1]->update([
            'status' => AiEscalation::STATUS_DIJAWAB,
            'dijawab_pada' => Carbon::parse('2026-06-10 12:00:00'),
        ]);

        Carbon::setTestNow('2026-06-15 09:00:00');
        $laporan = app(LaporanEskalasi::class)->bulanan(now());

        $this->assertSame('2026-06', $laporan['periode']);
        $this->assertSame(7, $laporan['total'], 'Eskalasi bulan lalu tidak boleh terhitung');
        $this->assertSame(
            ['level_fatwa' => 4, 'blacklist_topik' => 3],
            $laporan['volume_per_kategori'],
        );
        $this->assertSame(2, $laporan['jumlah_dijawab']);
        $this->assertSame(3.0, $laporan['rata_rata_jawab_jam']);

        // Contoh anonim: maksimal 5 (config), dan SELALU dari
        // pertanyaan_ringkas yang sudah terredaksi sejak ditulis T-A020.
        $this->assertCount(5, $laporan['contoh_anonim']);
        $semuaPertanyaanJuni = $bulanIni->pluck('pertanyaan_ringkas')->all();
        foreach ($laporan['contoh_anonim'] as $contoh) {
            $this->assertContains($contoh, $semuaPertanyaanJuni);
        }

        // Bulan Mei berisi tepat 1 eskalasi.
        $this->assertSame(1, app(LaporanEskalasi::class)->bulanan('2026-05-01')['total']);
    }

    public function test_perintah_laporan_mengirim_notifikasi_pimpinan_idempoten(): void
    {
        Carbon::setTestNow('2026-05-20 10:00:00');
        $tenant = $this->siapkanTenant('aiesk8');

        $this->buatEskalasi();
        $this->buatEskalasi(['kategori' => 'blacklist_topik']);

        // Dijadwalkan tanggal 1 Juni → laporan bulan Mei.
        Carbon::setTestNow('2026-06-01 06:00:00');
        tenancy()->end();

        $this->artisan('ai:laporan-eskalasi', ['tenant' => $tenant->id])->assertExitCode(0);

        tenancy()->initialize($tenant);

        $notif = LogNotifikasi::query()
            ->where('jenis', 'ai_laporan_eskalasi')
            ->where('target', 'pimpinan')
            ->get();
        $this->assertCount(1, $notif);
        $this->assertSame('2026-05', $notif->first()->payload['laporan']['periode']);
        $this->assertSame(2, $notif->first()->payload['laporan']['total']);

        // Idempoten per (tenant, periode): jalan ulang tidak menggandakan.
        tenancy()->end();
        $this->artisan('ai:laporan-eskalasi', ['tenant' => $tenant->id])->assertExitCode(0);
        tenancy()->initialize($tenant);

        $this->assertSame(1, LogNotifikasi::query()
            ->where('jenis', 'ai_laporan_eskalasi')
            ->count());
    }

    // ------------------------------------------------------------------
    // RBAC halaman + isolasi tenant
    // ------------------------------------------------------------------

    public function test_rbac_halaman_inbox_eskalasi(): void
    {
        $this->siapkanTenant('aiesk9');

        $this->assertTrue(Route::has('filament.tenant.pages.eskalasi-tanya-ustadz'));

        // Tanpa izin: tertutup.
        $this->actingAs($this->buatUser('polos9@test.local'));
        $this->assertFalse(EskalasiTanyaUstadz::canAccess());

        // Pemegang kelola (ustadz/admin): terbuka + halaman dapat dirender.
        $this->actingAs($this->buatUser('ustadz9@test.local', ['ai.eskalasi.kelola']));
        $this->assertTrue(EskalasiTanyaUstadz::canAccess());
        Livewire::test(EskalasiTanyaUstadz::class)->assertOk();

        // Role asatidz mewarisi ai.eskalasi.kelola dari seeder.
        $this->actingAs($this->buatUser('asatidz9@test.local', [], ['asatidz']));
        $this->assertTrue(EskalasiTanyaUstadz::canAccess());

        // Kyai/pimpinan (ai.setting.setujui): terbuka untuk laporan.
        $this->actingAs($this->buatUser('kyai9@test.local', ['ai.setting.setujui']));
        $this->assertTrue(EskalasiTanyaUstadz::canAccess());
        Livewire::test(EskalasiTanyaUstadz::class)->assertOk();
    }

    public function test_antrean_eskalasi_terisolasi_antar_tenant(): void
    {
        $this->siapkanTenant('aieska');
        $this->buatEskalasi();
        $this->assertSame(1, AiEscalation::query()->count());
        tenancy()->end();

        $this->siapkanTenant('aieskb');
        $this->assertSame(0, AiEscalation::query()->count(), 'Antrean tenant A bocor ke tenant B');
    }
}
