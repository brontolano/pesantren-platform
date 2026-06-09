<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Notifikasi\Models\BroadcastWa;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\TagihanPpdb;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class AlbaKidsLandingRegistrationTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->configurePpdbTestingDatabase();
        $this->ensurePpdbTestingSchemaReady([
            'tenants',
            'domains',
            'settings',
            'website_sections',
            'website_settings',
            'calon_santri',
            'tagihan_ppdb',
            'broadcast_wa',
            'template_notifikasi',
            'log_notifikasi',
        ]);

        $this->truncatePpdbTables([
            'log_notifikasi',
            'broadcast_wa',
            'template_notifikasi',
            'tagihan_ppdb',
            'calon_santri',
            'website_sections',
            'website_settings',
            'settings',
            'domains',
            'tenants',
            'users',
            'roles',
            'permissions',
            'model_has_roles',
            'model_has_permissions',
            'role_has_permissions',
        ]);
    }

    public function test_albakids_same_domain_renders_landing_page_with_inline_form(): void
    {
        $tenant = $this->makeAlbaTenant('landing');
        $tenant->domains()->where('domain', 'albakids.pesantren.online')->update(['type' => 'website', 'is_primary' => true]);
        $tenant->run(function (): void {
            Setting::updateOrCreate(['key' => 'admission_inline_enabled'], ['value' => ['enabled' => true]]);
        });

        $response = $this->call('GET', 'http://albakids.pesantren.online/', [], [], [], [
            'HTTP_HOST' => 'albakids.pesantren.online',
            'SERVER_NAME' => 'albakids.pesantren.online',
        ]);

        $response->assertOk();
        $response->assertSee('Alba Kids Pre-School');

        $page = $response->viewData('page');
        $this->assertTrue($page['props']['website']['admission']['enabled']);
        $this->assertSame('/daftar', $page['props']['website']['admission']['endpoint']);
        $this->assertSame('#form-pendaftaran', $page['props']['website']['sections'][0]['config']['primaryCtaHref']);
        $this->assertSame('Isi Form Pendaftaran', $page['props']['website']['sections'][0]['config']['primaryCtaLabel']);
    }

    public function test_same_domain_admin_login_still_resolves_for_albakids(): void
    {
        $tenant = $this->makeAlbaTenant('admin');
        $tenant->domains()->where('domain', 'albakids.pesantren.online')->update(['type' => 'website', 'is_primary' => true]);
        $tenant->run(function (): void {
            Setting::updateOrCreate(['key' => 'admission_inline_enabled'], ['value' => ['enabled' => true]]);
        });

        $response = $this->call('GET', 'http://albakids.pesantren.online/admin/login', [], [], [], [
            'HTTP_HOST' => 'albakids.pesantren.online',
            'SERVER_NAME' => 'albakids.pesantren.online',
        ]);

        $response->assertOk();
    }

    public function test_inline_registration_persists_candidate_proof_and_two_way_notifications(): void
    {
        $tenant = $this->makeAlbaTenant('submit');
        $tenant->domains()->where('domain', 'albakids.pesantren.online')->update(['type' => 'website', 'is_primary' => true]);

        $tenant->run(function (): void {
            Setting::updateOrCreate(['key' => 'admission_inline_enabled'], ['value' => ['enabled' => true]]);
            Setting::updateOrCreate(['key' => 'admission_admin_whatsapp'], ['value' => ['text' => '081234567890']]);
            Setting::updateOrCreate(['key' => 'admission_success_message'], ['value' => ['text' => 'Tim kami segera memverifikasi data Anda.']]);
        });

        Config::set('filesystems.default', 'public');
        Storage::fake('public');
        Http::fake([
            '*' => Http::response(['status' => 'ok'], 200),
        ]);

        $response = $this->call('POST', 'http://albakids.pesantren.online/daftar', [
            'nama_anak' => 'Alya Zahra',
            'nama_panggilan' => 'Alya',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '2021-01-10',
            'jenis_kelamin' => 'P',
            'kelas_tujuan' => 'TK A',
            'program_diminati' => 'TK A',
            'asal_sekolah' => 'PAUD Ceria',
            'nama_ayah' => 'Bapak Alya',
            'nama_ibu' => 'Ibu Alya',
            'nama_wali' => 'Ibu Alya',
            'hubungan_wali' => 'Ibu',
            'no_hp_wali' => '081111111111',
            'email_wali' => 'wali@alba.test',
            'alamat' => 'Jl. Ceria No. 1',
            'kota_domisili' => 'Bandung',
            'nama_pengirim_transfer' => 'Ibu Alya',
            'bank_pengirim' => 'BCA',
            'nominal_transfer' => '350000',
            'tanggal_transfer' => '2026-06-08',
            'sumber_informasi' => 'Instagram',
            'catatan_tambahan' => 'Anak aktif dan suka menggambar.',
            'bukti_transfer' => UploadedFile::fake()->create('bukti.png', 120, 'image/png'),
        ], [], [], [
            'HTTP_HOST' => 'albakids.pesantren.online',
            'SERVER_NAME' => 'albakids.pesantren.online',
        ]);

        $response->assertRedirect();

        $tenant->run(function (): void {
            $candidate = CalonSantri::query()->firstOrFail();
            $this->assertSame('Alya Zahra', $candidate->nama);
            $this->assertSame('menunggu_verifikasi', $candidate->status_pendaftaran);
            $this->assertSame('TK A', $candidate->program_diminati);

            $tagihan = TagihanPpdb::query()->firstOrFail();
            $this->assertSame('pendaftaran_albakids', $tagihan->tipe);
            $this->assertSame('menunggu_verifikasi', $tagihan->status);
            $this->assertNotNull($tagihan->bukti_transfer);
            Storage::disk('public')->assertExists($tagihan->bukti_transfer);

            $this->assertSame(2, BroadcastWa::query()->count());
        });

        Http::assertSentCount(2);
    }

    public function test_inline_registration_requires_proof_and_required_fields(): void
    {
        $tenant = $this->makeAlbaTenant('validate');
        $tenant->domains()->where('domain', 'albakids.pesantren.online')->update(['type' => 'website', 'is_primary' => true]);
        $tenant->run(function (): void {
            Setting::updateOrCreate(['key' => 'admission_inline_enabled'], ['value' => ['enabled' => true]]);
        });

        $response = $this->from('http://albakids.pesantren.online/')
            ->call('POST', 'http://albakids.pesantren.online/daftar', [
                'nama_anak' => '',
                'no_hp_wali' => '',
            ], [], [], [
                'HTTP_HOST' => 'albakids.pesantren.online',
                'SERVER_NAME' => 'albakids.pesantren.online',
            ]);

        $response->assertSessionHasErrors([
            'nama_anak',
            'tempat_lahir',
            'tanggal_lahir',
            'jenis_kelamin',
            'kelas_tujuan',
            'program_diminati',
            'nama_ibu',
            'nama_wali',
            'hubungan_wali',
            'no_hp_wali',
            'alamat',
            'nama_pengirim_transfer',
            'bank_pengirim',
            'nominal_transfer',
            'tanggal_transfer',
            'bukti_transfer',
        ]);
    }

    private function makeAlbaTenant(string $suffix): \App\Models\Tenant
    {
        $tenant = $this->createProvisionedTenant(
            'albakids-'.$suffix.'-'.str()->lower(str()->random(5)),
            'Alba Kids Pre-School',
            'albakids.pesantren.online'
        );
        $tenant->domains()->where('domain', 'albakids.pesantren.online')->update(['type' => 'website', 'is_primary' => true]);

        return $tenant;
    }
}
