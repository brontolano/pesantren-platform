<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Kegiatan\Models\Kegiatan;
use Modules\Kesantrian\Models\JenisPelanggaran;
use Modules\Kesantrian\Models\PelanggaranSantri;
use Modules\Kesantrian\Models\PengurusSantri;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\DisiplinService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-21 — Mode Pengurus-Santri di App Santri (/app/pengurus/*).
 *
 * (a) pengurus catat pelanggaran → SELALU 'dicatat' (walau wajib_verifikasi=false);
 * (b) presensi hanya kegiatan ber-qism cocok; tanpa qism / qism lain → 403;
 * (c) santri biasa (bukan pengurus) → 403;
 * (d) masa jabatan habis / nonaktif → 403 otomatis (cek per-request, tanpa cron).
 */
class Wp21PengurusSantriTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'settings', 'roles',
            'jenis_pelanggaran', 'pelanggaran_santri', 'pengurus_santri',
            'kegiatan', 'presensi_kegiatan',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    /** @return array{0:Tenant,1:Santri,2:User} */
    private function boot(string $prefix): array
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        if (! DB::connection('tenant')->table('roles')->where('name', 'santri')->exists()) {
            DB::connection('tenant')->table('roles')->insert([
                'name' => 'santri', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Pengurus '.$prefix,
            'email' => 'pengurus-'.$prefix.'@santri.test', 'password' => 'rahasia123',
        ]);
        $user->assignRole('santri');

        $santri = Santri::create([
            'nama' => 'Pengurus '.$prefix, 'nis' => 'PG-'.$prefix, 'status' => 'aktif',
            'tanggal_lahir' => '2010-01-01', 'jenis_kelamin' => 'L', 'user_id' => $user->id,
        ]);

        return [$tenant, $santri, $user];
    }

    private function host(Tenant $t): array
    {
        $h = $t->id.'.localhost';

        return [$h, ['HTTP_HOST' => $h, 'SERVER_NAME' => $h]];
    }

    private function jadikanPengurus(Santri $santri, string $qism, array $override = []): PengurusSantri
    {
        return PengurusSantri::create(array_merge([
            'santri_id' => $santri->id,
            'qism' => $qism,
            'label' => 'Qism '.ucfirst($qism),
            'periode_mulai' => now()->subMonth()->toDateString(),
            'periode_selesai' => now()->addMonths(5)->toDateString(),
            'aktif' => true,
        ], $override));
    }

    public function test_pengurus_catat_pelanggaran_selalu_dicatat_walau_setting_auto_verify(): void
    {
        [$tenant, $santri, $user] = $this->boot('pg1');
        $this->jadikanPengurus($santri, 'keamanan');

        // Saklar tenant: TANPA wajib verifikasi (ustadz auto-verify) —
        // tapi pengurus BUKAN ustadz: harus tetap 'dicatat'.
        Setting::create(['key' => DisiplinService::SETTING_WAJIB_VERIFIKASI, 'value' => false]);

        $terlapor = Santri::create([
            'nama' => 'Terlapor', 'nis' => 'TL-pg1', 'status' => 'aktif',
            'tanggal_lahir' => '2011-01-01', 'jenis_kelamin' => 'L',
        ]);
        $jenis = JenisPelanggaran::create(['nama' => 'Terlambat', 'kategori' => 'ringan', 'poin' => 5, 'aktif' => true]);

        tenancy()->end();
        [$h, $server] = $this->host($tenant);

        $res = $this->actingAs($user)->post("http://{$h}/app/pengurus/pelanggaran", [
            'santri_id' => $terlapor->id,
            'jenis_pelanggaran_id' => $jenis->id,
            'kronologi' => 'Terlambat shalat berjamaah.',
        ], $server);
        $res->assertRedirect();

        tenancy()->initialize($tenant);
        $row = PelanggaranSantri::query()->where('santri_id', $terlapor->id)->firstOrFail();
        $this->assertSame('dicatat', $row->status);
        $this->assertNull($row->verifikator_id);
    }

    public function test_presensi_hanya_kegiatan_qism_sendiri(): void
    {
        [$tenant, $santri, $user] = $this->boot('pg2');
        $this->jadikanPengurus($santri, 'kebersihan');

        $milik = Kegiatan::create(['nama' => 'Ro\'an Jumat', 'jenis' => 'rutin', 'qism' => 'kebersihan', 'mulai' => now(), 'status' => 'berjalan']);
        $qismLain = Kegiatan::create(['nama' => 'Mahkamah Bahasa', 'jenis' => 'rutin', 'qism' => 'bahasa', 'mulai' => now(), 'status' => 'berjalan']);
        $tanpaQism = Kegiatan::create(['nama' => 'Pengajian Umum', 'jenis' => 'rutin', 'mulai' => now(), 'status' => 'berjalan']);
        $peserta = Santri::create(['nama' => 'Peserta', 'nis' => 'PS-pg2', 'status' => 'aktif', 'tanggal_lahir' => '2011-01-01', 'jenis_kelamin' => 'L']);

        tenancy()->end();
        [$h, $server] = $this->host($tenant);
        $payload = ['santri_id' => $peserta->id, 'status' => 'hadir'];

        // Domain sendiri → sukses.
        $this->actingAs($user)
            ->post("http://{$h}/app/pengurus/kegiatan/{$milik->id}/presensi", $payload, $server)
            ->assertRedirect();

        // Qism lain → 403; tanpa qism → 403.
        $this->actingAs($user)
            ->post("http://{$h}/app/pengurus/kegiatan/{$qismLain->id}/presensi", $payload, $server)
            ->assertForbidden();
        $this->actingAs($user)
            ->post("http://{$h}/app/pengurus/kegiatan/{$tanpaQism->id}/presensi", $payload, $server)
            ->assertForbidden();

        tenancy()->initialize($tenant);
        $this->assertSame(1, DB::connection('tenant')->table('presensi_kegiatan')->count());
    }

    public function test_santri_biasa_tanpa_penugasan_403(): void
    {
        [$tenant, , $user] = $this->boot('pg3');
        tenancy()->end();
        [$h, $server] = $this->host($tenant);

        $this->actingAs($user)
            ->call('GET', "http://{$h}/app/pengurus", [], [], [], $server)
            ->assertForbidden();
    }

    public function test_masa_jabatan_habis_atau_nonaktif_403(): void
    {
        [$tenant, $santri, $user] = $this->boot('pg4');

        // Periode SUDAH lewat.
        $this->jadikanPengurus($santri, 'ibadah', [
            'periode_mulai' => now()->subYear()->toDateString(),
            'periode_selesai' => now()->subDay()->toDateString(),
        ]);
        // Baris kedua: periode jalan tapi aktif=false (dicabut).
        $this->jadikanPengurus($santri, 'bahasa', ['aktif' => false]);

        tenancy()->end();
        [$h, $server] = $this->host($tenant);

        $this->actingAs($user)
            ->call('GET', "http://{$h}/app/pengurus", [], [], [], $server)
            ->assertForbidden();
    }

    public function test_pengurus_aktif_bisa_buka_beranda_pengurus(): void
    {
        [$tenant, $santri, $user] = $this->boot('pg5');
        $this->jadikanPengurus($santri, 'keamanan', ['periode_selesai' => null]); // null = sampai dicabut

        tenancy()->end();
        [$h, $server] = $this->host($tenant);

        $this->actingAs($user)
            ->call('GET', "http://{$h}/app/pengurus", [], [], [], $server)
            ->assertOk();
    }
}
