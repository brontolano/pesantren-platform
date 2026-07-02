<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
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

    public function test_biodata_wizard_page_renders_for_owned_peserta(): void
    {
        tenancy()->initialize($this->tenant);
        $user = $this->makePeserta('biodata@spmb.test');
        $this->makeCandidate($user, [
            'nomor_pendaftaran' => 'PPDB-2026-505',
            'nama' => 'Calon Biodata',
        ]);
        tenancy()->end();

        $response = $this
            ->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->call('GET', "http://{$this->host}/spmb/biodata", [], [], [], [
                'HTTP_HOST' => $this->host,
                'SERVER_NAME' => $this->host,
            ]);

        $response->assertOk();
        $response->assertSee('PPDB\\/Portal\\/Biodata', false);
        $response->assertSee('PPDB-2026-505');
    }

    public function test_peserta_can_update_own_biodata_into_real_columns(): void
    {
        tenancy()->initialize($this->tenant);

        // nik dijadikan wajib via konfigurasi tenant.
        Setting::create([
            'tenant_id' => $this->tenant->id,
            'key' => 'ppdb_form_fields',
            'value' => ['fields' => ['nik' => 'required']],
        ]);

        $user = $this->makePeserta('edit@spmb.test');
        $candidate = $this->makeCandidate($user, [
            'nomor_pendaftaran' => 'PPDB-2026-202',
            'nama' => 'Nama Lama',
        ]);

        tenancy()->end();

        $response = $this
            ->actingAs($user)
            ->from("http://{$this->host}/spmb")
            ->put("http://{$this->host}/spmb/biodata", [
                // Pribadi (wajib)
                'nama' => 'Nama Baru',
                'nik' => '3216549870123456',
                'tempat_lahir' => 'Subang',
                'tanggal_lahir' => '2012-02-03',
                'jenis_kelamin' => 'P',
                // Alamat (wajib)
                'alamat' => 'Jl. Mawar No. 1',
                'rt_rw' => '003/008',
                'provinsi' => 'Jawa Barat',
                'kabupaten_kota' => 'Kabupaten Subang',
                'kecamatan' => 'Subang',
                'desa_kelurahan' => 'Karanganyar',
                'kode_pos' => '41211',
                // Pendidikan (wajib)
                'asal_sekolah' => 'MTs Baru',
                'jenjang_asal' => 'SMP/MTs',
                // Ortu (nama wajib)
                'nama_ayah' => 'Bapak Baru',
                'pekerjaan_ayah' => 'Petani',
                'penghasilan_ayah' => 'Rp1.000.000 - Rp3.000.000',
                'nama_ibu' => 'Ibu Baru',
                // Wali (no_hp_wali wajib)
                'nama_wali' => 'Ibu Baru',
                'hubungan_wali' => 'Ibu Kandung',
                'no_hp_wali' => '081234567890',
                'email_wali' => 'ortu@contoh.id',
                // Alamat domisili ayah (opsional, terpisah)
                'alamat_ayah' => 'Jl. Ayah No. 9',
                'provinsi_ayah' => 'Jawa Barat',
                'kabupaten_kota_ayah' => 'Kabupaten Subang',
                // Penanggung biaya
                'pembiaya' => 'Ayah',
                // Kesehatan (opsional)
                'golongan_darah' => 'O',
                // Field 'staf' — TIDAK boleh tertulis oleh peserta.
                'nisn' => '9999999999',
            ], [
                'HTTP_HOST' => $this->host,
                'SERVER_NAME' => $this->host,
            ]);

        $response->assertRedirect("http://{$this->host}/spmb");
        $response->assertSessionHas('success');

        tenancy()->initialize($this->tenant);
        $updated = $candidate->fresh();
        tenancy()->end();

        // Tersimpan di KOLOM ASLI (bukan data_ortu).
        $this->assertSame('Nama Baru', $updated->nama);
        $this->assertSame('3216549870123456', $updated->nik);
        $this->assertSame('Subang', $updated->tempat_lahir);
        $this->assertSame('P', $updated->jenis_kelamin);
        $this->assertSame('Jl. Mawar No. 1', $updated->alamat);
        $this->assertSame('Jawa Barat', $updated->provinsi);
        $this->assertSame('MTs Baru', $updated->asal_sekolah);
        $this->assertSame('SMP/MTs', $updated->jenjang_asal);
        $this->assertSame('Bapak Baru', $updated->nama_ayah);
        $this->assertSame('Petani', $updated->pekerjaan_ayah);
        $this->assertSame('Rp1.000.000 - Rp3.000.000', $updated->penghasilan_ayah);
        $this->assertSame('Ibu Baru', $updated->nama_ibu);
        $this->assertSame('Ibu Kandung', $updated->hubungan_wali);
        $this->assertSame('081234567890', $updated->no_hp_wali);
        $this->assertSame('ortu@contoh.id', $updated->email_wali);
        $this->assertSame('Jl. Ayah No. 9', $updated->alamat_ayah);
        $this->assertSame('Jawa Barat', $updated->provinsi_ayah);
        $this->assertSame('Ayah', $updated->pembiaya);
        $this->assertSame('O', $updated->golongan_darah);

        // Field 'staf' DIABAIKAN — peserta tidak bisa menulis NISN.
        $this->assertNull($updated->nisn);
    }

    public function test_wilayah_provinsi_endpoint_returns_json_array_for_peserta(): void
    {
        tenancy()->initialize($this->tenant);
        $user = $this->makePeserta('wilayah@spmb.test');
        $this->makeCandidate($user, ['nomor_pendaftaran' => 'PPDB-2026-404']);
        tenancy()->end();

        $response = $this
            ->actingAs($user)
            ->call('GET', "http://{$this->host}/spmb/wilayah/provinsi", [], [], [], [
                'HTTP_HOST' => $this->host,
                'SERVER_NAME' => $this->host,
                'HTTP_ACCEPT' => 'application/json',
            ]);

        $response->assertOk();
        $this->assertIsArray($response->json());
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
