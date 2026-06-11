<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Modules\Akademik\Models\Lembaga;
use Modules\Payroll\Database\Seeders\JabatanSeeder;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\Jabatan;
use Modules\Payroll\Models\PenugasanPegawai;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-03 — Master Jabatan + Penugasan Multi-Peran + sinkron role Spatie
 * (Blueprint SDM S-0 poin 1-2-4; pola test WP-01 LembagaFondasiTest).
 *
 * (a) Rangkap 3 jabatan di 2 lembaga sah.
 * (b) Sinkron role: assign saat ditugaskan; tutup 1 dari 2 penugasan
 *     ber-role sama → role bertahan; tutup keduanya → role dicabut.
 * (c) Dua is_primary aktif ditolak (partial unique); riwayat bebas.
 * (d) Employee tanpa user tertaut → penugasan jalan tanpa error.
 * (e) JabatanSeeder idempoten + tidak menimpa suntingan tenant.
 * (f) CHECK kategori jabatan.
 */
class PenugasanPegawaiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles',
            'employee', 'jabatan', 'penugasan_pegawai', 'lembaga',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function bootTenant(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $tenant;
    }

    private function buatPegawai(Tenant $tenant, string $suffix, bool $denganUser = true): Employee
    {
        $user = null;
        if ($denganUser) {
            $user = User::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Pegawai '.$suffix,
                'email' => "pegawai-{$suffix}@test.local",
                'password' => 'password123',
            ]);
        }

        return Employee::create([
            'nama' => 'Pegawai '.$suffix,
            'nip' => 'NIP-'.$suffix,
            'users_id' => $user?->id,
        ]);
    }

    // ---------------------------------------------------------------
    // (a) Rangkap 3 jabatan di 2 lembaga sah
    // ---------------------------------------------------------------

    public function test_rangkap_tiga_jabatan_dua_lembaga_sah(): void
    {
        $tenant = $this->bootTenant('pgrk');
        $pegawai = $this->buatPegawai($tenant, 'rangkap');

        $mts = Lembaga::create(['nama' => 'MTs', 'jenis' => 'formal']);
        $madin = Lembaga::create(['nama' => 'Madin', 'jenis' => 'diniyah']);

        $guru = Jabatan::create(['nama' => 'Guru Mapel', 'kategori' => 'pengajar', 'role_spatie' => 'guru']);
        $waka = Jabatan::create(['nama' => 'Waka Kurikulum X', 'kategori' => 'struktural', 'role_spatie' => 'guru']);
        $mustahiq = Jabatan::create(['nama' => 'Mustahiq X', 'kategori' => 'pengajar', 'role_spatie' => 'guru']);

        PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $guru->id, 'lembaga_id' => $mts->id, 'tanggal_mulai' => '2026-07-01', 'is_primary' => true]);
        PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $waka->id, 'lembaga_id' => $mts->id, 'tanggal_mulai' => '2026-07-01']);
        PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $mustahiq->id, 'lembaga_id' => $madin->id, 'tanggal_mulai' => '2026-07-01']);

        $this->assertSame(3, $pegawai->penugasan()->count(), 'Rangkap 3 jabatan di 2 lembaga harus sah');
        $this->assertSame('Guru Mapel', $pegawai->jabatanAktif()?->nama, 'jabatanAktif = penugasan is_primary');
    }

    // ---------------------------------------------------------------
    // (b) Sinkron role Spatie
    // ---------------------------------------------------------------

    public function test_penugasan_jabatan_ber_role_memberi_role_dan_revoke_hanya_saat_tak_ada_penugasan_lain(): void
    {
        $tenant = $this->bootTenant('pgrl');
        $pegawai = $this->buatPegawai($tenant, 'role');
        $user = $pegawai->user;

        $guru = Jabatan::create(['nama' => 'Guru A', 'kategori' => 'pengajar', 'role_spatie' => 'guru']);
        $mustahiq = Jabatan::create(['nama' => 'Mustahiq A', 'kategori' => 'pengajar', 'role_spatie' => 'guru']);

        $this->assertFalse($user->hasRole('guru'));

        $p1 = PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $guru->id, 'tanggal_mulai' => '2026-07-01']);
        $p2 = PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $mustahiq->id, 'tanggal_mulai' => '2026-07-01']);

        $this->assertTrue($user->fresh()->hasRole('guru'), 'Penugasan jabatan ber-role → user dapat role');

        // Tutup SATU dari dua penugasan ber-role sama → role bertahan.
        $p1->update(['tanggal_selesai' => '2026-12-31']);
        $this->assertTrue($user->fresh()->hasRole('guru'), 'Masih ada penugasan aktif lain ber-role sama → role bertahan');

        // Tutup keduanya → role dicabut.
        $p2->update(['tanggal_selesai' => '2026-12-31']);
        $this->assertFalse($user->fresh()->hasRole('guru'), 'Tak ada lagi penugasan aktif ber-role → role dicabut');
    }

    public function test_role_manual_di_luar_penugasan_tidak_ikut_tercabut_saat_tutup_penugasan_tanpa_role(): void
    {
        $tenant = $this->bootTenant('pgmn');
        $pegawai = $this->buatPegawai($tenant, 'manual');
        $pegawai->user->assignRole('tu'); // diberi manual oleh admin

        $lurah = Jabatan::create(['nama' => 'Lurah X', 'kategori' => 'pengurus_santri', 'role_spatie' => null]);
        $p = PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $lurah->id, 'tanggal_mulai' => '2026-07-01']);
        $p->update(['tanggal_selesai' => '2026-12-31']);

        $this->assertTrue($pegawai->user->fresh()->hasRole('tu'), 'Jabatan tanpa role_spatie tidak menyentuh role manual');
    }

    public function test_pindah_jabatan_memindahkan_role(): void
    {
        $tenant = $this->bootTenant('pgpj');
        $pegawai = $this->buatPegawai($tenant, 'pindah');
        $user = $pegawai->user;

        $guru = Jabatan::create(['nama' => 'Guru B', 'kategori' => 'pengajar', 'role_spatie' => 'guru']);
        $bendahara = Jabatan::create(['nama' => 'Bendahara B', 'kategori' => 'staf', 'role_spatie' => 'bendahara']);

        $p = PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $guru->id, 'tanggal_mulai' => '2026-07-01']);
        $this->assertTrue($user->fresh()->hasRole('guru'));

        $p->update(['jabatan_id' => $bendahara->id]);

        $user = $user->fresh();
        $this->assertFalse($user->hasRole('guru'), 'Pindah jabatan: role lama dilepas');
        $this->assertTrue($user->hasRole('bendahara'), 'Pindah jabatan: role baru diberikan');
    }

    // ---------------------------------------------------------------
    // (c) Partial unique is_primary
    // ---------------------------------------------------------------

    public function test_primary_baru_auto_demote_primary_lama_riwayat_bebas(): void
    {
        $tenant = $this->bootTenant('pgpr');
        $pegawai = $this->buatPegawai($tenant, 'primary');
        $j1 = Jabatan::create(['nama' => 'J1', 'kategori' => 'staf']);
        $j2 = Jabatan::create(['nama' => 'J2', 'kategori' => 'staf']);

        // Riwayat primary (sudah selesai) TIDAK menghalangi primary aktif baru.
        $riwayat = PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $j1->id, 'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'is_primary' => true]);
        $lama = PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $j1->id, 'tanggal_mulai' => '2026-07-01', 'is_primary' => true]);

        // Set primary baru → primary aktif lama auto-demote (bukan error 500).
        $baru = PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $j2->id, 'tanggal_mulai' => '2026-07-01', 'is_primary' => true]);

        $this->assertTrue($baru->fresh()->is_primary);
        $this->assertFalse($lama->fresh()->is_primary, 'Primary aktif lama diturunkan otomatis');
        $this->assertTrue($riwayat->fresh()->is_primary, 'Riwayat (selesai) tidak disentuh');
        $this->assertSame('J2', $pegawai->jabatanAktif()?->nama);
    }

    public function test_partial_unique_index_tetap_jadi_backstop_race(): void
    {
        $tenant = $this->bootTenant('pgrc');
        $pegawai = $this->buatPegawai($tenant, 'race');
        $j1 = Jabatan::create(['nama' => 'J1', 'kategori' => 'staf']);

        PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $j1->id, 'tanggal_mulai' => '2026-07-01', 'is_primary' => true]);

        // Insert mentah (melewati observer, meniru request kedua dalam race)
        // → partial unique index menolak primary aktif kedua di level DB.
        $this->expectException(QueryException::class);

        \Illuminate\Support\Facades\DB::table('penugasan_pegawai')->insert([
            'tenant_id' => $tenant->id, 'employee_id' => $pegawai->id, 'jabatan_id' => $j1->id,
            'tanggal_mulai' => '2026-07-01', 'is_primary' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------
    // (d) Employee tanpa user tertaut → tidak error
    // ---------------------------------------------------------------

    public function test_employee_tanpa_user_tidak_error_saat_ditugaskan_dan_ditutup(): void
    {
        $tenant = $this->bootTenant('pgnu');
        $pegawai = $this->buatPegawai($tenant, 'nouser', denganUser: false);
        $guru = Jabatan::create(['nama' => 'Guru C', 'kategori' => 'pengajar', 'role_spatie' => 'guru']);

        $p = PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $guru->id, 'tanggal_mulai' => '2026-07-01']);
        $p->update(['tanggal_selesai' => '2026-12-31']);

        $this->assertSame(1, $pegawai->penugasan()->count(), 'Penugasan pegawai tanpa user harus tetap tercatat tanpa error');
    }

    public function test_jabatan_dengan_role_tak_terdaftar_dilewati_tanpa_error(): void
    {
        $tenant = $this->bootTenant('pgrx');
        $pegawai = $this->buatPegawai($tenant, 'rolex');
        $aneh = Jabatan::create(['nama' => 'Jabatan Aneh', 'kategori' => 'staf', 'role_spatie' => 'role_tidak_ada']);

        PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $aneh->id, 'tanggal_mulai' => '2026-07-01']);

        $this->assertFalse($pegawai->user->fresh()->hasRole('super_admin'));
        $this->assertSame(0, $pegawai->user->fresh()->roles()->count(), 'Role tak terdaftar dilewati — observer tidak membuat role baru');
    }

    // ---------------------------------------------------------------
    // (g) Jalur licik observer — adversarial
    // ---------------------------------------------------------------

    public function test_tutup_dan_pindah_jabatan_sekaligus_mencabut_role_lama_tanpa_memberi_role_baru(): void
    {
        $tenant = $this->bootTenant('pgtp');
        $pegawai = $this->buatPegawai($tenant, 'tutuppindah');
        $guru = Jabatan::create(['nama' => 'Guru D', 'kategori' => 'pengajar', 'role_spatie' => 'guru']);
        $bendahara = Jabatan::create(['nama' => 'Bendahara D', 'kategori' => 'staf', 'role_spatie' => 'bendahara']);

        $p = PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $guru->id, 'tanggal_mulai' => '2026-07-01']);

        // Licik: ganti jabatan DAN tutup dalam satu update.
        $p->update(['jabatan_id' => $bendahara->id, 'tanggal_selesai' => '2026-12-31']);

        $user = $pegawai->user->fresh();
        $this->assertFalse($user->hasRole('guru'), 'Role jabatan lama dicabut');
        $this->assertFalse($user->hasRole('bendahara'), 'Penugasan langsung tertutup TIDAK memberi role baru');
    }

    public function test_hapus_penugasan_aktif_mencabut_role(): void
    {
        $tenant = $this->bootTenant('pghp');
        $pegawai = $this->buatPegawai($tenant, 'hapus');
        $guru = Jabatan::create(['nama' => 'Guru E', 'kategori' => 'pengajar', 'role_spatie' => 'guru']);

        $p = PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $guru->id, 'tanggal_mulai' => '2026-07-01']);
        $this->assertTrue($pegawai->user->fresh()->hasRole('guru'));

        $p->delete();

        $this->assertFalse($pegawai->user->fresh()->hasRole('guru'), 'Hapus baris penugasan aktif → role dicabut');
    }

    public function test_penugasan_dibuat_sudah_selesai_tidak_memberi_role(): void
    {
        $tenant = $this->bootTenant('pgcs');
        $pegawai = $this->buatPegawai($tenant, 'closedcreate');
        $guru = Jabatan::create(['nama' => 'Guru F', 'kategori' => 'pengajar', 'role_spatie' => 'guru']);

        PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $guru->id, 'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30']);

        $this->assertFalse($pegawai->user->fresh()->hasRole('guru'), 'Penugasan riwayat (dibuat sudah selesai) tidak memberi role');
    }

    public function test_ubah_role_spatie_jabatan_me_resync_role_user_penugasan_aktif(): void
    {
        $tenant = $this->bootTenant('pgrs');
        $pegawai = $this->buatPegawai($tenant, 'resync');
        $jabatan = Jabatan::create(['nama' => 'Staf G', 'kategori' => 'staf', 'role_spatie' => 'tu']);

        PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $jabatan->id, 'tanggal_mulai' => '2026-07-01']);
        $this->assertTrue($pegawai->user->fresh()->hasRole('tu'));

        // Admin mengubah mapping role jabatan → role lama tidak boleh orphan.
        $jabatan->update(['role_spatie' => 'bendahara']);

        $user = $pegawai->user->fresh();
        $this->assertFalse($user->hasRole('tu'), 'Role lama dicabut saat mapping jabatan diubah');
        $this->assertTrue($user->hasRole('bendahara'), 'Role baru diberikan ke pemegang penugasan aktif');

        // Mapping dihapus (NULL) → role dicabut tanpa pengganti.
        $jabatan->update(['role_spatie' => null]);
        $this->assertFalse($pegawai->user->fresh()->hasRole('bendahara'));
    }

    public function test_ganti_users_id_employee_memindahkan_role_turunan_penugasan(): void
    {
        $tenant = $this->bootTenant('pggu');
        $pegawai = $this->buatPegawai($tenant, 'gantiuser');
        $userLama = $pegawai->user;
        $guru = Jabatan::create(['nama' => 'Guru H', 'kategori' => 'pengajar', 'role_spatie' => 'guru']);

        PenugasanPegawai::create(['employee_id' => $pegawai->id, 'jabatan_id' => $guru->id, 'tanggal_mulai' => '2026-07-01']);
        $this->assertTrue($userLama->fresh()->hasRole('guru'));

        $userBaru = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Akun Baru',
            'email' => 'akun-baru@test.local', 'password' => 'password123',
        ]);

        $pegawai->update(['users_id' => $userBaru->id]);

        $this->assertFalse($userLama->fresh()->hasRole('guru'), 'Role dicabut dari akun lama saat pegawai pindah akun');
        $this->assertTrue($userBaru->fresh()->hasRole('guru'), 'Role pindah ke akun baru');
    }

    public function test_pindah_employee_pada_penugasan_memindahkan_role(): void
    {
        $tenant = $this->bootTenant('pgpe');
        $pegawaiA = $this->buatPegawai($tenant, 'empA');
        $pegawaiB = $this->buatPegawai($tenant, 'empB');
        $guru = Jabatan::create(['nama' => 'Guru I', 'kategori' => 'pengajar', 'role_spatie' => 'guru']);

        $p = PenugasanPegawai::create(['employee_id' => $pegawaiA->id, 'jabatan_id' => $guru->id, 'tanggal_mulai' => '2026-07-01']);
        $this->assertTrue($pegawaiA->user->fresh()->hasRole('guru'));

        // Licik: penugasan dipindah ke pegawai lain lewat edit employee_id.
        $p->update(['employee_id' => $pegawaiB->id]);

        $this->assertFalse($pegawaiA->user->fresh()->hasRole('guru'), 'Role dicabut dari pegawai lama');
        $this->assertTrue($pegawaiB->user->fresh()->hasRole('guru'), 'Role diberikan ke pegawai baru');
    }

    // ---------------------------------------------------------------
    // (e) Seeder jabatan idempoten
    // ---------------------------------------------------------------

    public function test_seeder_jabatan_idempoten_dan_tidak_menimpa_suntingan_tenant(): void
    {
        $this->bootTenant('pgsd');

        Artisan::call('db:seed', ['--class' => JabatanSeeder::class, '--force' => true]);
        $jumlah = Jabatan::query()->count();
        $this->assertSame(count(JabatanSeeder::JABATAN), $jumlah);

        // Suntingan tenant: ganti mapping role Mudir → NULL; nonaktifkan Lurah Pondok.
        Jabatan::query()->where('nama', 'Mudir')->update(['role_spatie' => null]);
        Jabatan::query()->where('nama', 'Lurah Pondok')->update(['aktif' => false]);

        Artisan::call('db:seed', ['--class' => JabatanSeeder::class, '--force' => true]);

        $this->assertSame($jumlah, Jabatan::query()->count(), 'Seeder dua kali tidak boleh dobel');
        $this->assertNull(Jabatan::query()->where('nama', 'Mudir')->value('role_spatie'), 'Suntingan tenant tidak ditimpa seeder');
        $this->assertFalse((bool) Jabatan::query()->where('nama', 'Lurah Pondok')->value('aktif'), 'Jabatan yang dinonaktifkan tenant TIDAK hidup lagi saat reprovision');

        // Mapping role_spatie HANYA ke role yang terdaftar.
        $terdaftar = \Spatie\Permission\Models\Role::query()->pluck('name');
        Jabatan::query()->whereNotNull('role_spatie')->pluck('role_spatie')
            ->each(fn ($role) => $this->assertTrue($terdaftar->contains($role), "role_spatie '{$role}' tidak terdaftar di RolesAndPermissionsSeeder"));
    }

    // ---------------------------------------------------------------
    // (f) CHECK kategori
    // ---------------------------------------------------------------

    public function test_kategori_di_luar_daftar_ditolak_check_constraint(): void
    {
        $this->bootTenant('pgck');

        $this->expectException(QueryException::class);

        Jabatan::create(['nama' => 'Aneh', 'kategori' => 'direktur_utama']);
    }
}
