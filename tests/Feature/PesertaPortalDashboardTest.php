<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\PPDB\Database\Seeders\PesertaRoleSeeder;
use Modules\PPDB\Models\CalonSantri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PesertaPortalDashboardTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;
    private string $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'users',
            'settings',
            'tenant_storage',
            'roles',
            'model_has_roles',
            'calon_santri',
            'domains',
            'tenants',
        ]);

        $this->truncatePpdbTables([
            'domains',
            'tenants',
            'tenant_storage',
        ]);

        $suffix = Str::lower(Str::random(6));
        $tenantId = 'spmb-'.$suffix;
        $this->host = $tenantId.'.localhost';

        $this->tenant = $this->createProvisionedTenant($tenantId, 'SPMB Dashboard', $this->host);

        tenancy()->initialize($this->tenant);
        $this->seed(PesertaRoleSeeder::class);
        tenancy()->end();
    }

    public function test_peserta_dashboard_renders_owned_candidate_on_tenant_domain(): void
    {
        tenancy()->initialize($this->tenant);
        $user = $this->makePeserta('wali@spmb.test');
        $candidate = $this->makeCandidate($user, [
            'nomor_pendaftaran' => 'PPDB-2026-101',
            'nama' => 'Ahmad Peserta',
            'status_pendaftaran' => 'pending',
        ]);
        tenancy()->end();

        $response = $this
            ->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->call('GET', "http://{$this->host}/spmb", [], [], [], [
                'HTTP_HOST' => $this->host,
                'SERVER_NAME' => $this->host,
            ]);

        $response->assertOk();
        $response->assertSee('PPDB\\/Portal\\/Dashboard', false);
        $response->assertSee('PPDB-2026-101');
        $response->assertSee('Ahmad Peserta');
    }

    public function test_peserta_can_update_own_biodata_using_tenant_field_configuration(): void
    {
        tenancy()->initialize($this->tenant);

        Setting::create([
            'tenant_id' => $this->tenant->id,
            'key' => 'ppdb_form_fields',
            'value' => [
                'fields' => [
                    'nik' => 'required',
                    'catatan_tambahan' => 'hidden',
                ],
            ],
        ]);

        $user = $this->makePeserta('edit@spmb.test');
        $candidate = $this->makeCandidate($user, [
            'nomor_pendaftaran' => 'PPDB-2026-202',
            'nama' => 'Nama Lama',
            'data_ortu' => [
                'nik' => null,
                'jenjang_asal' => 'SD/MI',
                'hubungan' => 'Ayah',
                'catatan_tambahan' => 'Lama',
            ],
        ]);

        tenancy()->end();

        $response = $this
            ->actingAs($user)
            ->from("http://{$this->host}/spmb")
            ->put("http://{$this->host}/spmb/biodata", [
                'nama' => 'Nama Baru',
                'nik' => '3216549870123456',
                'tempatLahir' => 'Subang',
                'tanggalLahir' => '2012-02-03',
                'jenisKelamin' => 'Perempuan',
                'jenjangAsal' => 'SMP/MTs',
                'sekolahAsal' => 'MTs Baru',
                'namaWali' => 'Ibu Baru',
                'hubunganWali' => 'Ibu',
                'noHpWali' => '081234567890',
                'emailWali' => 'ortu@contoh.id',
                'programDiminati' => 'Tahfidz',
                'catatanTambahan' => 'Tidak dipakai karena hidden',
            ], [
                'HTTP_HOST' => $this->host,
                'SERVER_NAME' => $this->host,
            ]);

        $response->assertRedirect("http://{$this->host}/spmb");
        $response->assertSessionHas('success');

        tenancy()->initialize($this->tenant);
        $updated = $candidate->fresh();
        tenancy()->end();

        $this->assertSame('Nama Baru', $updated->nama);
        $this->assertSame('Subang', $updated->tempat_lahir);
        $this->assertSame('P', $updated->jenis_kelamin);
        $this->assertSame('MTs Baru', $updated->asal_sekolah);
        $this->assertSame('Ibu Baru', $updated->nama_ayah);
        $this->assertSame('081234567890', $updated->no_hp_wali);
        $this->assertSame('ortu@contoh.id', $updated->email_wali);
        $this->assertSame('Tahfidz', $updated->program_diminati);
        $this->assertSame('3216549870123456', $updated->data_ortu['nik']);
        $this->assertSame('SMP/MTs', $updated->data_ortu['jenjang_asal']);
        $this->assertSame('Ibu', $updated->data_ortu['hubungan']);
        $this->assertNull($updated->data_ortu['catatan_tambahan']);
    }

    public function test_peserta_cannot_update_other_participants_data(): void
    {
        tenancy()->initialize($this->tenant);
        $owner = $this->makePeserta('owner@spmb.test');
        $other = $this->makePeserta('other@spmb.test');
        $this->makeCandidate($other, [
            'nomor_pendaftaran' => 'PPDB-2026-303',
            'nama' => 'Peserta Lain',
        ]);
        tenancy()->end();

        $response = $this
            ->actingAs($owner)
            ->put("http://{$this->host}/spmb/biodata", [
                'nama' => 'Nama Tidak Jadi Disimpan',
                'tanggalLahir' => '2012-02-03',
                'jenisKelamin' => 'Laki-laki',
                'jenjangAsal' => 'SD/MI',
                'sekolahAsal' => 'SDIT Aman',
                'namaWali' => 'Ayah Aman',
                'noHpWali' => '081222222222',
                'programDiminati' => 'Reguler',
            ], [
                'HTTP_HOST' => $this->host,
                'SERVER_NAME' => $this->host,
            ]);

        $response->assertSessionHasErrors('auth');
    }

    private function makePeserta(string $email): User
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Peserta Test',
            'email' => $email,
            'phone' => null,
            'password' => 'password123',
            'must_reset_password' => false,
        ]);

        $user->assignRole(PesertaRoleSeeder::ROLE);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeCandidate(User $user, array $overrides = []): CalonSantri
    {
        return CalonSantri::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'nomor_pendaftaran' => 'PPDB-2026-000',
            'nama' => 'Calon Default',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status_pendaftaran' => 'pending',
            'program_diminati' => 'Reguler',
            'data_ortu' => [
                'nik' => null,
                'jenjang_asal' => null,
                'hubungan' => null,
                'catatan_tambahan' => null,
            ],
        ], $overrides));
    }
}
