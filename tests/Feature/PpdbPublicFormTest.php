<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PpdbPublicFormTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->truncatePpdbTables([
            'settings',
            'tagihan_ppdb',
            'hasil_ujian',
            'calon_santri_dokumen',
            'calon_santri',
            'domains',
            'tenants',
        ]);
    }

    public function test_public_ppdb_form_is_accessible_without_login_on_tenant_domain(): void
    {
        $tenant = $this->createTestTenant('asy-syifaa', 'Asy-Syifaa', 'asy-syifaa');

        $response = $this
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->call('GET', 'http://asy-syifaa.localhost/ppdb', [], [], [], [
                'HTTP_HOST' => 'asy-syifaa.localhost',
                'SERVER_NAME' => 'asy-syifaa.localhost',
            ]);

        $response->assertOk();
        $response->assertSee('PPDB\\/PublicForm', false);
        $response->assertSee('Asy-Syifaa');
    }

    public function test_public_ppdb_form_receives_tenant_field_configuration(): void
    {
        $tenant = $this->createTestTenant('config-ppdb', 'PPDB Config', 'config-ppdb');

        tenancy()->initialize($tenant);

        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'ppdb_form_fields',
            'value' => [
                'fields' => [
                    'nik' => 'hidden',
                    'catatan_tambahan' => 'required',
                ],
            ],
        ]);

        tenancy()->end();

        $response = $this->call('GET', 'http://config-ppdb.localhost/ppdb', [], [], [], [
                'HTTP_HOST' => 'config-ppdb.localhost',
                'SERVER_NAME' => 'config-ppdb.localhost',
            ]);

        $response->assertOk();

        $page = $response->viewData('page');

        $this->assertSame('hidden', $page['props']['fieldConfiguration']['nik']['mode']);
        $this->assertSame('required', $page['props']['fieldConfiguration']['catatan_tambahan']['mode']);
        $this->assertSame('required', $page['props']['fieldConfiguration']['nama']['mode']);
    }

    public function test_public_ppdb_form_submit_persists_candidate_for_current_tenant(): void
    {
        $tenant = $this->createTestTenant('submit-ppdb', 'PPDB Submit', 'submit-ppdb');

        $response = $this->from('http://submit-ppdb.localhost/ppdb')->post('http://submit-ppdb.localhost/ppdb', [
            'nama' => 'Calon Baru',
            'tanggalLahir' => '2012-07-07',
            'jenisKelamin' => 'Laki-laki',
            'jenjangAsal' => 'SD/MI',
            'sekolahAsal' => 'SDIT Contoh',
            'namaWali' => 'Ahmad Wali',
            'noHpWali' => '081234567890',
            'programDiminati' => 'Tahfidz',
        ], [
            'HTTP_HOST' => 'submit-ppdb.localhost',
            'SERVER_NAME' => 'submit-ppdb.localhost',
        ]);

        $response->assertRedirect('http://submit-ppdb.localhost/ppdb');
        $response->assertSessionHas('success');

        $candidate = DB::table('calon_santri')->where('tenant_id', 'submit-ppdb')->first();

        $this->assertNotNull($candidate);
        $this->assertSame('Calon Baru', $candidate->nama);
        $this->assertSame('L', $candidate->jenis_kelamin);
        $this->assertSame('Tahfidz', $candidate->program_diminati);
        $this->assertSame('pending', $candidate->status_pendaftaran);
    }
}
