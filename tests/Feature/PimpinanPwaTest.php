<?php

namespace Tests\Feature;

use App\Http\Controllers\PimpinanPwaController;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Services\ApprovalService;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kesantrian\Models\JenisPelanggaran;
use Modules\Kesantrian\Models\PelanggaranSantri;
use Modules\Kesantrian\Models\PresensiAsrama;
use Modules\Kesantrian\Models\Santri;
use Modules\Ledger\Services\BukuBesarService;
use Modules\Ledger\Services\LaporanService;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\PresensiStaf;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-35 — Dasbor & Approval Pimpinan PWA (/pimpinan):
 * gerbang akses_pimpinan, angka kas & kehadiran cocok seed, antrean approval
 * hanya untuk pemegang role approver, dan aksi setujui memakai ApprovalService.
 */
class PimpinanPwaTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    private User $kepala;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->configurePpdbTestingDatabase();
        if (! Schema::connection('pgsql')->hasTable('approval_request') || ! Schema::connection('pgsql')->hasTable('presensi_asrama')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(): void
    {
        $this->tenant = $this->createRuntimeTenant('wp35', 'Pesantren WP35');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        (new RolesAndPermissionsSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->kepala = User::withoutGlobalScopes()->create([
            'name' => 'Kyai', 'email' => 'kyai@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);
        $this->kepala->assignRole('kepala');
    }

    private function host(): string
    {
        return $this->tenant->id.'.localhost';
    }

    public function test_seeder_memberi_akses_pimpinan_ke_kepala(): void
    {
        $this->boot();

        $this->assertTrue($this->kepala->can('akses_pimpinan'));

        tenancy()->end();
    }

    public function test_tanpa_akses_pimpinan_mendapat_403(): void
    {
        $this->boot();

        $guru = User::withoutGlobalScopes()->create([
            'name' => 'Guru Biasa', 'email' => 'guru@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);
        $guru->givePermissionTo('akses_pwa_guru');

        $this->actingAs($guru)
            ->get('http://'.$this->host().'/pimpinan')
            ->assertForbidden();

        $this->actingAs($this->kepala)
            ->get('http://'.$this->host().'/pimpinan')
            ->assertOk();

        tenancy()->end();
    }

    public function test_ringkasan_kas_dan_kehadiran_cocok_seed(): void
    {
        $this->boot();

        $pencatat = User::withoutGlobalScopes()->create([
            'name' => 'Musyrif', 'email' => 'musyrif@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);

        // Seed kehadiran santri hari ini: kelas 2 hadir + 1 alpa; asrama 1 hadir.
        $santris = collect(range(1, 3))->map(fn (int $i) => Santri::create([
            'tenant_id' => tenant('id'), 'nama' => "Santri {$i}", 'nis' => "WP35-{$i}",
            'tanggal_lahir' => '2011-01-01', 'jenis_kelamin' => 'L',
        ]));

        PresensiSantri::create(['tenant_id' => tenant('id'), 'santri_id' => $santris[0]->id, 'tanggal' => now()->toDateString(), 'status' => 'hadir']);
        PresensiSantri::create(['tenant_id' => tenant('id'), 'santri_id' => $santris[1]->id, 'tanggal' => now()->toDateString(), 'status' => 'hadir']);
        PresensiSantri::create(['tenant_id' => tenant('id'), 'santri_id' => $santris[2]->id, 'tanggal' => now()->toDateString(), 'status' => 'alpa']);
        PresensiAsrama::create([
            'tenant_id' => tenant('id'), 'santri_id' => $santris[0]->id, 'tanggal' => now()->toDateString(),
            'slot' => 'subuh', 'status' => 'hadir', 'pencatat_id' => $pencatat->id,
        ]);

        // Guru: 1 hadir + 1 izin hari ini.
        $e1 = Employee::create(['tenant_id' => tenant('id'), 'nama' => 'Ust A']);
        $e2 = Employee::create(['tenant_id' => tenant('id'), 'nama' => 'Ust B']);
        PresensiStaf::create(['tenant_id' => tenant('id'), 'employee_id' => $e1->id, 'tanggal' => now()->toDateString(), 'status' => 'hadir']);
        PresensiStaf::create(['tenant_id' => tenant('id'), 'employee_id' => $e2->id, 'tanggal' => now()->toDateString(), 'status' => 'izin']);

        // Antrean: 1 izin diajukan + 1 pelanggaran menunggu verifikasi.
        IzinSantri::create([
            'tenant_id' => tenant('id'), 'santri_id' => $santris[0]->id, 'jenis_izin' => 'pulang',
            'tanggal_mulai' => now()->addDays(7)->toDateString(), 'tanggal_selesai' => now()->addDays(9)->toDateString(),
            'alasan' => 'Acara keluarga', 'status' => 'diajukan',
        ]);
        $jenis = JenisPelanggaran::create(['tenant_id' => tenant('id'), 'nama' => 'Terlambat', 'kategori' => 'ringan', 'poin' => 5, 'aktif' => true]);
        PelanggaranSantri::create([
            'tenant_id' => tenant('id'), 'santri_id' => $santris[1]->id, 'jenis_pelanggaran_id' => $jenis->id,
            'waktu_kejadian' => now(), 'kronologi' => 'Terlambat sholat', 'pencatat_id' => $pencatat->id, 'status' => 'dicatat',
        ]);

        Auth::login($this->kepala);
        $controller = new PimpinanPwaController;
        $data = $controller->ringkasan(app(LaporanService::class), app(BukuBesarService::class))->getData(true);

        // Kas: belum ada jurnal → 0 (angka sama dengan dasbor PWA Keuangan).
        $this->assertEqualsWithDelta(0, $data['kas']['saldo'], 0.01);

        // Kehadiran santri: (2 hadir kelas + 1 hadir asrama) / 4 catatan = 75%.
        $this->assertSame(4, $data['kehadiran']['santri']['total']);
        $this->assertSame(3, $data['kehadiran']['santri']['hadir']);
        $this->assertEqualsWithDelta(75.0, $data['kehadiran']['santri']['persen'], 0.01);

        // Guru: 1/2 = 50%.
        $this->assertSame(2, $data['kehadiran']['guru']['total']);
        $this->assertEqualsWithDelta(50.0, $data['kehadiran']['guru']['persen'], 0.01);

        // Mutu defensif: tabel mutu_lampu belum ada → null (section disembunyikan).
        $this->assertNull($data['mutu']);

        // Antrean baca-saja.
        $this->assertSame(1, $data['antrean']['izin_diajukan']);
        $this->assertSame(1, $data['antrean']['pelanggaran_menunggu']);

        tenancy()->end();
    }

    public function test_antrean_approval_hanya_untuk_pemegang_role_approver(): void
    {
        $this->boot();

        Role::firstOrCreate(['name' => 'bendahara']);

        // Dua request menunggu: satu utk role 'kepala', satu utk 'bendahara'.
        ApprovalRequest::create([
            'tenant_id' => tenant('id'), 'approvable_type' => 'Modules\\Pengadaan\\Models\\Belanja',
            'approvable_id' => 1, 'status' => 'menunggu', 'current_step' => 1, 'approver_role' => 'kepala',
        ]);
        ApprovalRequest::create([
            'tenant_id' => tenant('id'), 'approvable_type' => 'Modules\\Pengadaan\\Models\\Belanja',
            'approvable_id' => 2, 'status' => 'menunggu', 'current_step' => 1, 'approver_role' => 'bendahara',
        ]);

        Auth::login($this->kepala);
        $controller = new PimpinanPwaController;
        $data = $controller->ringkasan(app(LaporanService::class), app(BukuBesarService::class))->getData(true);

        $this->assertCount(1, $data['antrean']['approval'], 'Kepala hanya melihat antrean ber-role kepala.');
        $this->assertSame('kepala', $data['antrean']['approval'][0]['approver_role']);

        // Pimpinan ber-akses_pimpinan TANPA role approver mana pun → antrean kosong.
        $pimpinanLain = User::withoutGlobalScopes()->create([
            'name' => 'Pengawas', 'email' => 'pengawas@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);
        $pimpinanLain->givePermissionTo('akses_pimpinan');

        Auth::login($pimpinanLain);
        $data2 = $controller->ringkasan(app(LaporanService::class), app(BukuBesarService::class))->getData(true);
        $this->assertCount(0, $data2['antrean']['approval']);

        tenancy()->end();
    }

    public function test_setujui_dan_tolak_lewat_pwa_memakai_approval_service(): void
    {
        $this->boot();

        $req = ApprovalRequest::create([
            'tenant_id' => tenant('id'), 'approvable_type' => 'Modules\\Pengadaan\\Models\\Belanja',
            'approvable_id' => 10, 'status' => 'menunggu', 'current_step' => 1, 'approver_role' => 'kepala',
        ]);

        // Approve dari PWA → status berubah lewat engine (diputus_oleh terisi).
        $this->actingAs($this->kepala)
            ->postJson('http://'.$this->host()."/pimpinan/api/approval/{$req->id}/setujui")
            ->assertOk()
            ->assertJson(['status' => 'disetujui']);

        $req->refresh();
        $this->assertSame('disetujui', $req->status);
        $this->assertSame($this->kepala->id, (int) $req->diputus_oleh);
        $this->assertNotNull($req->diputus_pada);

        // Request sudah diputus → engine menolak keputusan kedua (422, bukan 500).
        $this->actingAs($this->kepala)
            ->postJson('http://'.$this->host()."/pimpinan/api/approval/{$req->id}/setujui")
            ->assertStatus(422);

        // Tolak: wajib alasan + tercatat.
        $req2 = ApprovalRequest::create([
            'tenant_id' => tenant('id'), 'approvable_type' => 'Modules\\Pengadaan\\Models\\Belanja',
            'approvable_id' => 11, 'status' => 'menunggu', 'current_step' => 1, 'approver_role' => 'kepala',
        ]);

        $this->actingAs($this->kepala)
            ->postJson('http://'.$this->host()."/pimpinan/api/approval/{$req2->id}/tolak", [])
            ->assertStatus(422); // alasan wajib

        $this->actingAs($this->kepala)
            ->postJson('http://'.$this->host()."/pimpinan/api/approval/{$req2->id}/tolak", ['alasan' => 'Anggaran belum tersedia'])
            ->assertOk();

        $this->assertSame('ditolak', $req2->refresh()->status);
        $this->assertSame('Anggaran belum tersedia', $req2->alasan);

        tenancy()->end();
    }

    public function test_pimpinan_non_approver_tidak_boleh_memutus(): void
    {
        $this->boot();

        $req = ApprovalRequest::create([
            'tenant_id' => tenant('id'), 'approvable_type' => 'Modules\\Pengadaan\\Models\\Belanja',
            'approvable_id' => 20, 'status' => 'menunggu', 'current_step' => 1, 'approver_role' => 'bendahara',
        ]);

        // Kepala punya akses_pimpinan tapi BUKAN approver rule bendahara → 403.
        $this->actingAs($this->kepala)
            ->postJson('http://'.$this->host()."/pimpinan/api/approval/{$req->id}/setujui")
            ->assertForbidden();

        $this->assertSame('menunggu', $req->refresh()->status);

        tenancy()->end();
    }
}
