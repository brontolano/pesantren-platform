<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\AI\Models\AiApproval;
use Modules\AI\Models\AiEscalation;
use Modules\AI\Models\AiResearchEvent;
use Modules\AI\Models\AiTenantSetting;
use Modules\AI\Services\Escalation\EscalationService;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Modules\AI\Services\Settings\AiTenantSettingDefaults;
use Modules\AI\Services\Settings\TenantSettingService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A090 — komponen UI AI standar (<x-ai::...>):
 *  (a) render tiap komponen: string kunci + atribut aksesibilitas dasar
 *      (role/aria, target sentuh min-h-11/h-11 w-11, token kontras AA);
 *  (b) blok sitasi kosong tidak merender; indikator kredit tanpa anggaran
 *      = "tanpa batas"; persen melebihi 100 tetap terpotong di bar;
 *  (c) panel asisten: form-first + "Pengaturan lanjutan" collapsed default
 *      (progressive disclosure) + area status ber-aria-live;
 *  (d) tombol Tanya Ustadz → eskalasi manual idempoten per user+hash
 *      pertanyaan, terredaksi PII, kategori 'manual';
 *  (e) rating bintang → ResearchTelemetry::rekamRating: tercatat saat
 *      opt-in, NOL saat opt-out, rentang liar ditolak validasi (bukan 500);
 *  (f) kedua route ber-auth: guest ditolak (redirect login — exception
 *      dirender JSON hanya untuk path api/*, lihat bootstrap/app.php).
 */
class AiUiKomponenTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'ai_tenant_setting', 'ai_approval', 'ai_escalation',
            'ai_policy', 'ai_policy_rule', 'ai_research_event',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        // Baseline gating modul T-A012: registry kosong = modul tampil.
        DB::table('module_registry')->delete();
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

    private function buatUser(string $email, array $permissions = []): User
    {
        $user = User::create([
            'name' => 'Uji '.$email,
            'email' => $email,
            'password' => 'password',
        ]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
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

    private function urlTenant(Tenant $tenant, string $path): string
    {
        return 'http://'.$tenant->id.'.localhost'.$path;
    }

    // ------------------------------------------------------------------
    // (a)(b) Render komponen
    // ------------------------------------------------------------------

    public function test_badge_ai_tiga_varian_dengan_aksesibilitas_dasar(): void
    {
        $agama = Blade::render('<x-ai::badge-ai />');
        $this->assertStringContainsString('Jawaban AI — bukan fatwa/keputusan', $agama);
        $this->assertStringContainsString('role="note"', $agama);
        $this->assertStringContainsString('aria-label="Jawaban AI — bukan fatwa/keputusan"', $agama);
        // Token kontras AA (slate-800 di atas slate-100).
        $this->assertStringContainsString('#1e293b', $agama);
        $this->assertStringContainsString('#f1f5f9', $agama);

        $data = Blade::render('<x-ai::badge-ai varian="data" />');
        $this->assertStringContainsString('Jawaban AI — dihasilkan sistem', $data);

        $draf = Blade::render('<x-ai::badge-ai varian="draf" />');
        $this->assertStringContainsString('DRAF — perlu pengesahan', $draf);
        // Token kontras AA varian draf (amber-900 di atas amber-100).
        $this->assertStringContainsString('#78350f', $draf);
        $this->assertStringContainsString('#fef3c7', $draf);
    }

    public function test_blok_sitasi_merender_daftar_dan_kosong_tidak_render(): void
    {
        // Bentuk field sitasi AiGenerateResult (label deterministik RagAnswerer).
        $denganLabel = Blade::render(
            '<x-ai::blok-sitasi :sitasi="$sitasi" />',
            ['sitasi' => [
                ['judul' => 'Panduan Adab', 'bab' => 'Bab 2', 'halaman' => 14, 'label' => 'Panduan Adab — Bab 2, hlm. 14'],
            ]],
        );
        $this->assertStringContainsString('Sumber', $denganLabel);
        $this->assertStringContainsString('Panduan Adab — Bab 2, hlm. 14', $denganLabel);
        $this->assertStringContainsString('aria-label="Sumber jawaban"', $denganLabel);

        // Bentuk baris ai_citation (tanpa label) → disusun "Judul — Bab, hlm. N".
        $tanpaLabel = Blade::render(
            '<x-ai::blok-sitasi :sitasi="$sitasi" />',
            ['sitasi' => [['judul' => 'Profil Pesantren', 'bab' => null, 'halaman' => 3]]],
        );
        $this->assertStringContainsString('Profil Pesantren, hlm. 3', $tanpaLabel);

        // Kosong → tidak merender apa pun.
        $kosong = Blade::render('<x-ai::blok-sitasi :sitasi="[]" />');
        $this->assertSame('', trim($kosong), 'Sitasi kosong tidak boleh merender markup');
    }

    public function test_editor_draf_badge_tombol_sahkan_dan_pagar_manusia(): void
    {
        $html = Blade::render(
            '<x-ai::editor-draf aksi="sahkan"><textarea name="isi">Draf surat…</textarea></x-ai::editor-draf>',
        );

        $this->assertStringContainsString('DRAF — perlu pengesahan', $html, 'Badge DRAF wajib otomatis');
        $this->assertStringContainsString('wire:click="sahkan"', $html);
        $this->assertStringContainsString('Sahkan', $html);
        $this->assertStringContainsString('AI hanya menyusun draf; Anda yang memutuskan.', $html);
        $this->assertStringContainsString('Draf surat…', $html, 'Slot konten editor harus dirender');
        // Target sentuh tombol ≥ 44px + warna brand Forest Green.
        $this->assertStringContainsString('min-h-11', $html);
        $this->assertStringContainsString('#14532d', $html);

        // Tanpa aksi → tanpa tombol bawaan (pemakai bawa tombol sendiri).
        $polos = Blade::render('<x-ai::editor-draf>isi</x-ai::editor-draf>');
        $this->assertStringNotContainsString('wire:click', $polos);
    }

    public function test_indikator_kredit_persen_ambang_dan_tanpa_batas(): void
    {
        $normal = Blade::render('<x-ai::indikator-kredit :terpakai="80" :anggaran="200" />');
        $this->assertStringContainsString('role="progressbar"', $normal);
        $this->assertStringContainsString('aria-valuenow="40"', $normal);
        $this->assertStringContainsString('Kredit AI terpakai 80 dari 200 (40%)', $normal);
        $this->assertStringContainsString('#14532d', $normal, 'Di bawah ambang = hijau brand');

        // ≥ 100%: bar terpotong di 100, teks santun anggaran habis, bar merah.
        $habis = Blade::render('<x-ai::indikator-kredit :terpakai="260" :anggaran="200" />');
        $this->assertStringContainsString('aria-valuenow="100"', $habis);
        $this->assertStringContainsString('width: 100%', $habis);
        $this->assertStringContainsString('anggaran bulan ini telah habis', $habis);
        $this->assertStringContainsString('#b91c1c', $habis);

        // Tanpa anggaran (null) → "tanpa batas", tanpa progressbar.
        $bebas = Blade::render('<x-ai::indikator-kredit :terpakai="42" :anggaran="null" />');
        $this->assertStringContainsString('tanpa batas anggaran', $bebas);
        $this->assertStringNotContainsString('role="progressbar"', $bebas);
    }

    // ------------------------------------------------------------------
    // (c) Panel asisten form-first + progressive disclosure
    // ------------------------------------------------------------------

    public function test_panel_asisten_form_first_dengan_progressive_disclosure(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ai::panel-asisten
                judul="Generator Soal"
                deskripsi="Isi formulir, asisten menyusun draf soal."
                varian-badge="draf"
                :sitasi="[['label' => 'Kitab Contoh — Bab 1, hlm. 9']]"
            >
                <form id="form-utama"><input name="tema"></form>

                <x-slot:lanjutan>
                    <label>Jumlah opsi jawaban <input name="jumlah_opsi"></label>
                </x-slot:lanjutan>

                <x-slot:status>Sedang disusun, mohon menunggu…</x-slot:status>

                <x-slot:hasil>Draf soal nomor satu…</x-slot:hasil>

                <x-slot:kredit>
                    <x-ai::indikator-kredit :terpakai="10" :anggaran="100" />
                </x-slot:kredit>
            </x-ai::panel-asisten>
            BLADE);

        // Kepala panel + form-first.
        $this->assertStringContainsString('Generator Soal', $html);
        $this->assertStringContainsString('Isi formulir, asisten menyusun draf soal.', $html);
        $this->assertStringContainsString('form-utama', $html);

        // Progressive disclosure: collapsed default, ber-ARIA, Alpine toggle.
        $this->assertStringContainsString('Pengaturan lanjutan', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('x-show="buka"', $html);
        $this->assertStringContainsString('x-cloak', $html);
        $this->assertStringContainsString('aria-controls=', $html);

        // Area status async ber-aria-live (polling diserahkan ke pemakai).
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('Sedang disusun, mohon menunggu…', $html);

        // Hasil: badge varian draf + sitasi otomatis.
        $this->assertStringContainsString('Draf soal nomor satu…', $html);
        $this->assertStringContainsString('DRAF — perlu pengesahan', $html);
        $this->assertStringContainsString('Kitab Contoh — Bab 1, hlm. 9', $html);

        // Footer kredit.
        $this->assertStringContainsString('Kredit AI terpakai 10 dari 100', $html);
    }

    public function test_tombol_tanya_ustadz_dan_rating_merender_form_ke_route(): void
    {
        $this->assertTrue(Route::has('ai.tanya-ustadz.eskalasi'));
        $this->assertTrue(Route::has('ai.rating-jawaban'));

        $tombol = Blade::render('<x-ai::tombol-tanya-ustadz pertanyaan="Bagaimana adab bertamu?" />');
        $this->assertStringContainsString('/ai/tanya-ustadz', $tombol);
        $this->assertStringContainsString('method="POST"', $tombol);
        $this->assertStringContainsString('value="Bagaimana adab bertamu?"', $tombol);
        $this->assertStringContainsString('Tanya Ustadz', $tombol);
        $this->assertStringContainsString('min-h-11', $tombol, 'Target sentuh tombol ≥ 44px');
        $this->assertStringContainsString('antrean Tanya Ustadz', $tombol);

        $rating = Blade::render('<x-ai::rating-jawaban agent="chatbot_wali" />');
        $this->assertStringContainsString('/ai/rating-jawaban', $rating);
        $this->assertStringContainsString('value="chatbot_wali"', $rating);
        $this->assertSame(5, substr_count($rating, 'aria-label="Beri nilai'), 'Lima bintang ber-aria-label');
        $this->assertStringContainsString('aria-label="Beri nilai 5 dari 5"', $rating);
        $this->assertStringContainsString('h-11 w-11', $rating, 'Target sentuh bintang 44×44px');
    }

    // ------------------------------------------------------------------
    // (d) Tombol Tanya Ustadz → eskalasi manual idempoten + terredaksi
    // ------------------------------------------------------------------

    public function test_post_tanya_ustadz_membuat_eskalasi_manual_idempoten_terredaksi(): void
    {
        $tenant = $this->siapkanTenant('aiui1');
        $pengguna = $this->buatUser('wali-ui1@test.local');

        $pertanyaan = 'Mohon penjelasan adab menjenguk, hubungi saya di 081234567890.';

        $this->actingAs($pengguna)
            ->post($this->urlTenant($tenant, '/ai/tanya-ustadz'), ['pertanyaan' => $pertanyaan])
            ->assertRedirect();

        $this->assertSame(1, AiEscalation::query()->count());

        $eskalasi = AiEscalation::query()->firstOrFail();
        $this->assertSame(EscalationService::KATEGORI_MANUAL, $eskalasi->kategori);
        $this->assertSame('web', $eskalasi->kanal);
        $this->assertSame(AiEscalation::STATUS_BARU, $eskalasi->status);
        $this->assertStringContainsString('[PII:HP:1]', $eskalasi->pertanyaan_ringkas, 'Nomor HP wajib terredaksi');
        $this->assertStringNotContainsString('081234567890', $eskalasi->pertanyaan_ringkas);
        $this->assertNotNull($eskalasi->idempotency_key);

        // Klik ganda / kirim ulang pertanyaan yang sama → TIDAK menggandakan.
        $this->actingAs($pengguna)
            ->post($this->urlTenant($tenant, '/ai/tanya-ustadz'), ['pertanyaan' => $pertanyaan])
            ->assertRedirect();

        // Variasi spasi/kapital yang sama substansi → tetap satu antrean.
        $this->actingAs($pengguna)
            ->post($this->urlTenant($tenant, '/ai/tanya-ustadz'), [
                'pertanyaan' => '  MOHON   penjelasan adab menjenguk, hubungi saya di 081234567890.  ',
            ])
            ->assertRedirect();

        $this->assertSame(1, AiEscalation::query()->count(), 'Idempoten per user+hash pertanyaan');

        // Pertanyaan berbeda = antrean baru.
        $this->actingAs($pengguna)
            ->post($this->urlTenant($tenant, '/ai/tanya-ustadz'), ['pertanyaan' => 'Bagaimana tata cara izin pulang?'])
            ->assertRedirect();

        $this->assertSame(2, AiEscalation::query()->count());

        // Pengguna lain dengan pertanyaan sama = antrean tersendiri.
        $lain = $this->buatUser('wali-ui1b@test.local');
        $this->actingAs($lain)
            ->post($this->urlTenant($tenant, '/ai/tanya-ustadz'), ['pertanyaan' => $pertanyaan])
            ->assertRedirect();

        $this->assertSame(3, AiEscalation::query()->count(), 'Kunci idempoten terikat per user');

        // Default opt-out riset → NOL event telemetri (uji negatif T-A013).
        $this->assertSame(0, AiResearchEvent::query()
            ->where('tenant_id', (string) $tenant->id)->count());
    }

    public function test_post_tanya_ustadz_guest_ditolak_dan_pertanyaan_kosong_ditolak(): void
    {
        $tenant = $this->siapkanTenant('aiui2');

        // Guest: ditolak middleware auth → redirect ke login (bootstrap app
        // me-render exception sebagai JSON hanya untuk path api/*), dan
        // TIDAK ada baris antrean yang lahir.
        $this->post($this->urlTenant($tenant, '/ai/tanya-ustadz'), ['pertanyaan' => 'Uji tanpa login'])
            ->assertRedirect();
        $this->assertSame(0, AiEscalation::query()->count(), 'Guest tidak boleh membuat eskalasi');

        // Login tapi pertanyaan kosong: ditolak validasi (redirect balik +
        // error 'pertanyaan'), tetap tanpa baris baru — bukan error 500.
        $pengguna = $this->buatUser('wali-ui2@test.local');
        $this->actingAs($pengguna)
            ->post($this->urlTenant($tenant, '/ai/tanya-ustadz'), ['pertanyaan' => '   '])
            ->assertRedirect()
            ->assertSessionHasErrors('pertanyaan');

        $this->assertSame(0, AiEscalation::query()->count());
    }

    // ------------------------------------------------------------------
    // (e)(f) Rating jawaban → telemetri opt-in / opt-out + auth
    // ------------------------------------------------------------------

    public function test_post_rating_tercatat_saat_optin_dan_eskalasi_manual_menambah_event(): void
    {
        $tenant = $this->siapkanTenant('aiui3');
        $this->terapkanSetting(['optin_riset' => true]);

        $pengguna = $this->buatUser('wali-ui3@test.local');

        $this->actingAs($pengguna)
            ->postJson($this->urlTenant($tenant, '/ai/rating-jawaban'), [
                'agent' => 'chatbot_wali',
                'rating' => 4,
            ])
            ->assertOk()
            ->assertJsonStructure(['pesan']);

        $event = AiResearchEvent::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('jenis', AiResearchEvent::JENIS_RATING_JAWABAN)
            ->firstOrFail();
        $this->assertSame(['agent' => 'chatbot_wali', 'rating' => 4], $event->payload);

        // Eskalasi manual BARU ikut menambah event kategori 'manual'…
        $this->actingAs($pengguna)
            ->post($this->urlTenant($tenant, '/ai/tanya-ustadz'), ['pertanyaan' => 'Bagaimana jadwal kunjungan wali?'])
            ->assertRedirect();
        // …dan kirim ulang idempoten TIDAK menggandakan event.
        $this->actingAs($pengguna)
            ->post($this->urlTenant($tenant, '/ai/tanya-ustadz'), ['pertanyaan' => 'Bagaimana jadwal kunjungan wali?'])
            ->assertRedirect();

        $kategoriEvents = AiResearchEvent::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('jenis', AiResearchEvent::JENIS_ESKALASI_KATEGORI)
            ->get();
        $this->assertCount(1, $kategoriEvents);
        $this->assertSame(EscalationService::KATEGORI_MANUAL, $kategoriEvents->first()->payload['kategori']);
    }

    public function test_post_rating_nol_event_saat_optout_dan_rentang_liar_ditolak(): void
    {
        $tenant = $this->siapkanTenant('aiui4');

        // Guest ditolak middleware auth (redirect login — exception JSON
        // hanya untuk path api/*) — diuji SEBELUM actingAs.
        $this->post($this->urlTenant($tenant, '/ai/rating-jawaban'), [
            'agent' => 'chatbot_wali',
            'rating' => 3,
        ])->assertRedirect();

        $pengguna = $this->buatUser('wali-ui4@test.local');

        // Default provisioning = opt-out: respons tetap santun (tidak
        // membocorkan status opt-in), tapi NOL baris telemetri.
        $this->actingAs($pengguna)
            ->postJson($this->urlTenant($tenant, '/ai/rating-jawaban'), [
                'agent' => 'chatbot_wali',
                'rating' => 5,
            ])
            ->assertOk();

        $this->assertSame(0, AiResearchEvent::query()
            ->where('tenant_id', (string) $tenant->id)->count(), 'Tenant opt-out WAJIB nol event riset');

        // Rentang liar → ditolak VALIDASI (error 'rating', redirect balik) —
        // BUKAN 500 InvalidArgumentException dari service.
        $this->actingAs($pengguna)
            ->post($this->urlTenant($tenant, '/ai/rating-jawaban'), [
                'agent' => 'chatbot_wali',
                'rating' => 9,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('rating');

        // Tetap nol event — input liar tidak menulis apa pun.
        $this->assertSame(0, AiResearchEvent::query()
            ->where('tenant_id', (string) $tenant->id)->count());
    }
}
