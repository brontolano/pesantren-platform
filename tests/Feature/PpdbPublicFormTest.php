<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\PPDB\Services\PpdbStatusTrackingService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PpdbPublicFormTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants',
            'domains',
            'tenant_storage',
        ]);

        $this->truncatePpdbTables([
            'settings',
            'tagihan_ppdb',
            'hasil_ujian',
            'calon_santri_dokumen',
            'calon_santri',
            'domains',
            'tenants',
            'tenant_storage',
        ]);
    }

    public function test_public_ppdb_form_is_accessible_without_login_on_tenant_domain(): void
    {
        $suffix = Str::lower(Str::random(6));
        $subdomain = 'asy-'.$suffix;
        $host = $subdomain.'.localhost';
        $tenant = $this->createProvisionedTenant($subdomain, 'Asy-Syifaa', $host);

        $response = $this
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->call('GET', "http://{$host}/ppdb", [], [], [], [
                'HTTP_HOST' => $host,
                'SERVER_NAME' => $host,
            ]);

        $response->assertOk();
        $response->assertSee('PPDB\\/PublicForm', false);
        $response->assertSee('Asy-Syifaa');
    }

    public function test_public_ppdb_form_receives_minimal_form_options(): void
    {
        $suffix = Str::lower(Str::random(6));
        $subdomain = 'config-'.$suffix;
        $host = $subdomain.'.localhost';
        $this->createProvisionedTenant($subdomain, 'PPDB Config', $host);

        $response = $this->call('GET', "http://{$host}/ppdb", [], [], [], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $response->assertOk();

        $page = $response->viewData('page');
        $options = $page['props']['formOptions'];

        // Form publik kini SINGKAT & tetap (tak lagi digerakkan fieldConfiguration).
        $this->assertArrayNotHasKey('fieldConfiguration', $page['props']);
        $this->assertContains('SD/MI', $options['jenjangAsal']);
        $this->assertSame('L', $options['jenisKelamin'][0]['value']);
        // 'program diminati' sudah dihapus dari form.
        $this->assertArrayNotHasKey('programDiminati', $options);
    }

    public function test_public_ppdb_form_submit_persists_candidate_for_current_tenant(): void
    {
        $suffix = Str::lower(Str::random(6));
        $subdomain = 'submit-'.$suffix;
        $host = $subdomain.'.localhost';
        $tenant = $this->createProvisionedTenant($subdomain, 'PPDB Submit', $host);

        $response = $this->from("http://{$host}/ppdb")->post("http://{$host}/ppdb", [
            'nama' => 'Calon Baru',
            'jenisKelamin' => 'L',
            'tempatLahir' => 'Bandung',
            'tanggalLahir' => '2012-07-07',
            'jenjangAsal' => 'SD/MI',
            'asalSekolah' => 'SDIT Contoh',
            'namaWali' => 'Ahmad Wali',
            'noHpWali' => '081234567890',
            'emailWali' => 'wali@contoh.id',
            'setuju_kebijakan' => '1',
        ], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        tenancy()->initialize($tenant);
        $candidateNumber = DB::table('calon_santri')->value('nomor_pendaftaran');
        tenancy()->end();

        $response->assertRedirect("http://{$host}/spmb/aktivasi?pendaftaran={$candidateNumber}");
        $response->assertSessionHas('success');

        tenancy()->initialize($tenant);
        $candidate = DB::table('calon_santri')->first();
        tenancy()->end();

        $this->assertNotNull($candidate);
        $this->assertSame($candidateNumber, $candidate->nomor_pendaftaran);
        $this->assertSame('Calon Baru', $candidate->nama);
        $this->assertSame('L', $candidate->jenis_kelamin);
        $this->assertSame('SD/MI', $candidate->jenjang_asal);
        $this->assertSame('SDIT Contoh', $candidate->asal_sekolah);
        $this->assertSame('Ahmad Wali', $candidate->nama_wali);
        $this->assertSame('081234567890', $candidate->no_hp_wali);
        $this->assertSame('pending', $candidate->status_pendaftaran);
        $this->assertNotNull($candidate->consent_kebijakan_at);
    }

    public function test_public_tracking_page_is_accessible_without_login_and_offers_portal_cta(): void
    {
        $suffix = Str::lower(Str::random(6));
        $subdomain = 'track-'.$suffix;
        $host = $subdomain.'.localhost';
        $tenant = $this->createProvisionedTenant($subdomain, 'PPDB Tracking', $host);

        tenancy()->initialize($tenant);
        $candidate = DB::table('calon_santri')->insertGetId([
            'tenant_id' => $tenant->id,
            'nomor_pendaftaran' => 'PPDB-2026-TRACK',
            'nama' => 'Calon Tracking',
            'tanggal_lahir' => '2012-03-03',
            'jenis_kelamin' => 'L',
            'status_pendaftaran' => 'pending',
            'program_diminati' => 'Reguler',
            'nama_ayah' => 'Wali Tracking',
            'no_hp_wali' => '081234567891',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $token = app(PpdbStatusTrackingService::class)
            ->issueTrackingLink((int) $candidate)['tracking_token'];
        $storedToken = DB::table('calon_santri')->where('id', $candidate)->value('tracking_token');
        $this->assertSame($token, $storedToken);
        tenancy()->end();

        $response = $this
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->call('GET', "http://{$host}/ppdb/tracking/{$token}", [], [], [], [
                'HTTP_HOST' => $host,
                'SERVER_NAME' => $host,
            ]);

        $response->assertOk();
        $response->assertSee('PPDB\\/Portal\\/Tracking', false);
        $response->assertSee('PPDB-2026-TRACK');
        $response->assertSee('Calon Tracking');
        $response->assertSee('\\/spmb\\/login', false);
        $response->assertSee('\\/spmb\\/aktivasi?pendaftaran=PPDB-2026-TRACK', false);
    }
}
