<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Modules\PPDB\Database\Seeders\PesertaRoleSeeder;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\PaketSoal;
use Modules\PPDB\Models\UjianAttempt;
use Modules\PPDB\Services\UjianOnlineService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Audit keamanan portal Ujian Online (R-3/R-5, W4.1): isolasi attempt
 * antar-peserta, gate guest, dan kebocoran kunci jawaban ke client.
 */
class PortalUjianSecurityTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'users', 'settings', 'roles', 'model_has_roles',
            'calon_santri', 'paket_soal', 'soal', 'ujian_attempt',
            'domains', 'tenants',
        ]);
        $this->truncatePpdbTables(['ujian_attempt', 'soal', 'paket_soal', 'domains', 'tenants']);

        $suffix = Str::lower(Str::random(6));
        $tenantId = 'ujsec-'.$suffix;
        $this->host = $tenantId.'.localhost';
        $this->tenant = $this->createProvisionedTenant($tenantId, 'Ujian Security', $this->host);

        tenancy()->initialize($this->tenant);
        $this->seed(PesertaRoleSeeder::class);
        tenancy()->end();
    }

    public function test_guest_tidak_bisa_membuka_portal_ujian(): void
    {
        $response = $this->call('GET', "http://{$this->host}/spmb/ujian", [], [], [], [
            'HTTP_HOST' => $this->host,
            'SERVER_NAME' => $this->host,
        ]);

        $this->assertContains($response->status(), [302, 401, 403], 'Guest harus dialihkan/ditolak, bukan melihat ujian.');
    }

    public function test_kunci_jawaban_tidak_pernah_bocor_ke_halaman_kerjakan(): void
    {
        [$userA, , $paket] = $this->bootPesertaDenganAttempt();

        $response = $this
            ->actingAs($userA)
            ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->call('GET', "http://{$this->host}/spmb/ujian/{$paket->id}", [], [], [], [
                'HTTP_HOST' => $this->host,
                'SERVER_NAME' => $this->host,
            ]);

        $response->assertOk();
        $response->assertDontSee('"kunci"', false); // field kunci tak boleh ada di payload Inertia
    }

    public function test_peserta_lain_tidak_bisa_mengakses_attempt_milik_orang(): void
    {
        [, , $paket] = $this->bootPesertaDenganAttempt();

        tenancy()->initialize($this->tenant);
        $userB = $this->makePeserta('penyusup@ujsec.test');
        $this->makeCandidate($userB, 'UJSEC-B');
        tenancy()->end();

        $server = ['HTTP_HOST' => $this->host, 'SERVER_NAME' => $this->host];

        // B tidak punya attempt pada paket itu → attemptMilik() WAJIB 404,
        // tidak boleh jatuh ke attempt milik A.
        $this->actingAs($userB)
            ->call('GET', "http://{$this->host}/spmb/ujian/{$paket->id}", [], [], [], $server)
            ->assertNotFound();

        $this->actingAs($userB)
            ->call('POST', "http://{$this->host}/spmb/ujian/{$paket->id}/jawab", [
                'soal_id' => 1, 'jawaban' => 'A',
            ], [], [], $server)
            ->assertNotFound();

        $this->actingAs($userB)
            ->call('POST', "http://{$this->host}/spmb/ujian/{$paket->id}/selesai", [], [], [], $server)
            ->assertNotFound();

        // Attempt A tidak berubah oleh upaya penyusup.
        tenancy()->initialize($this->tenant);
        $this->assertSame('berjalan', UjianAttempt::query()->firstOrFail()->status);
        tenancy()->end();
    }

    /** @return array{0:User,1:CalonSantri,2:PaketSoal} */
    private function bootPesertaDenganAttempt(): array
    {
        tenancy()->initialize($this->tenant);
        $userA = $this->makePeserta('pemilik@ujsec.test');
        $calonA = $this->makeCandidate($userA, 'UJSEC-A');

        $paket = PaketSoal::create(['nama' => 'Paket Audit', 'kategori_ujian' => 'Tulis', 'durasi_menit' => 30]);
        $paket->soal()->create([
            'tipe' => 'pg', 'pertanyaan' => 'Soal audit?',
            'pilihan' => ['A' => 'Satu', 'B' => 'Dua'], 'kunci' => 'B', 'bobot' => 1, 'sort' => 1,
        ]);

        app(UjianOnlineService::class)->mulai($calonA, $paket);
        tenancy()->end();

        return [$userA, $calonA, $paket];
    }

    private function makePeserta(string $email): User
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Peserta Audit',
            'email' => $email,
            'phone' => null,
            'password' => 'password123',
            'must_reset_password' => false,
        ]);
        $user->assignRole(PesertaRoleSeeder::ROLE);

        return $user;
    }

    private function makeCandidate(User $user, string $nomor): CalonSantri
    {
        return CalonSantri::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'nomor_pendaftaran' => $nomor,
            'nama' => 'Calon '.$nomor,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status_pendaftaran' => 'pending',
        ]);
    }
}
