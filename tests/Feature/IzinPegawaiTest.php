<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Modules\Akademik\Models\Lembaga;
use Modules\Payroll\Models\Cuti;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\Jabatan;
use Modules\Payroll\Models\PenugasanPegawai;
use Modules\Payroll\Services\IzinPegawaiService;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-17 — Izin Pegawai Harian (di atas tabel cuti existing).
 *
 * (a) Approver = kepala lembaga dari penugasan is_primary aktif.
 * (b) Tanpa penugasan primary → fallback pemegang run_payroll → admin pertama.
 * (c) Ajukan idempoten via client_ref (retry → baris sama, tak dobel).
 * (d) Izin harian default is_berbayar TRUE (WP-14 tak memotong).
 * (e) Non-approver tak bisa setujui (error service + 403 HTTP).
 * (f) PWA: GET /guru/izin 200, POST ajukan 302 + tersimpan.
 */
class IzinPegawaiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'model_has_roles', 'model_has_permissions', 'settings',
            'employee', 'jabatan', 'penugasan_pegawai', 'lembaga',
            'cuti', 'approval_rule', 'approval_request',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function boot(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        return $tenant;
    }

    private function buatUser(string $nama): User
    {
        return User::withoutGlobalScopes()->create([
            'name' => $nama, 'email' => 'u'.str()->random(8).'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);
    }

    private function buatPegawai(string $nama): Employee
    {
        $u = $this->buatUser($nama);

        return Employee::create(['users_id' => $u->id, 'nama' => $nama, 'ptkp_status' => 'TK0', 'aktif' => true]);
    }

    /** Penugasan primary aktif di lembaga ber-kepala. */
    private function tugaskanPrimary(Employee $e, User $kepala): Lembaga
    {
        $lembaga = Lembaga::create(['nama' => 'MTs Uji', 'jenis' => 'formal', 'kepala_id' => $kepala->id]);
        $jabatan = Jabatan::create(['nama' => 'Guru', 'aktif' => true]);
        PenugasanPegawai::create([
            'employee_id' => $e->id, 'jabatan_id' => $jabatan->id, 'lembaga_id' => $lembaga->id,
            'tanggal_mulai' => '2026-01-01', 'is_primary' => true,
        ]);

        return $lembaga;
    }

    // ---------------------------------------------------------------
    // (a) Approver = kepala lembaga penugasan primary
    // ---------------------------------------------------------------

    public function test_approver_adalah_kepala_lembaga_dari_penugasan_primary(): void
    {
        $this->boot('izn1');
        $kepala = $this->buatUser('Kepala MTs');
        $e = $this->buatPegawai('Ust Fulan');
        $this->tugaskanPrimary($e, $kepala);

        $approver = app(IzinPegawaiService::class)->approverUntuk($e);

        $this->assertNotNull($approver);
        $this->assertSame($kepala->id, $approver->id);
    }

    // ---------------------------------------------------------------
    // (b) Tanpa penugasan primary → fallback
    // ---------------------------------------------------------------

    public function test_tanpa_penugasan_primary_approval_jatuh_ke_fallback_run_payroll(): void
    {
        $this->boot('izn2');
        $adminHr = $this->buatUser('Admin HR');
        Permission::create(['name' => 'run_payroll', 'guard_name' => 'web']);
        $adminHr->givePermissionTo('run_payroll');

        $e = $this->buatPegawai('Ust Tanpa Penugasan');

        $approver = app(IzinPegawaiService::class)->approverUntuk($e);

        $this->assertNotNull($approver, 'Fallback wajib menghasilkan approver');
        $this->assertSame($adminHr->id, $approver->id, 'Fallback = pemegang run_payroll pertama');
    }

    public function test_kepala_mengajukan_izin_sendiri_jatuh_ke_fallback(): void
    {
        $this->boot('izn3');
        $adminHr = $this->buatUser('Admin HR');
        Permission::create(['name' => 'run_payroll', 'guard_name' => 'web']);
        $adminHr->givePermissionTo('run_payroll');

        // Kepala lembaga adalah pegawai itu sendiri → tak boleh setujui sendiri.
        $e = $this->buatPegawai('Kepala Sekaligus Pegawai');
        $this->tugaskanPrimary($e, $e->user);

        $approver = app(IzinPegawaiService::class)->approverUntuk($e);
        $this->assertSame($adminHr->id, $approver->id);
    }

    // ---------------------------------------------------------------
    // (c)+(d) Ajukan idempoten + default berbayar
    // ---------------------------------------------------------------

    public function test_ajukan_idempoten_via_client_ref_dan_default_berbayar(): void
    {
        $this->boot('izn4');
        $e = $this->buatPegawai('Ust Mudik');
        $svc = app(IzinPegawaiService::class);

        $ref = (string) str()->uuid();
        $data = [
            'jenis' => 'pulang_kampung', 'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2026-07-02', 'alasan' => 'Mudik', 'client_ref' => $ref,
        ];

        $izin1 = $svc->ajukan($e, $data, $e->users_id);
        $izin2 = $svc->ajukan($e, $data, $e->users_id); // retry sinyal jelek

        $this->assertSame($izin1->id, $izin2->id, 'client_ref sama → baris sama');
        $this->assertSame(1, Cuti::query()->count(), 'Tidak boleh dobel');
        $this->assertSame('diajukan', $izin1->status);
        $this->assertTrue($izin1->is_berbayar, 'Izin harian DEFAULT berbayar — tak memotong gaji (WP-14)');
        $this->assertSame(2, $izin1->jumlah_hari);
    }

    public function test_jenis_tak_dikenal_ditolak(): void
    {
        $this->boot('izn5');
        $e = $this->buatPegawai('Ust X');

        $this->expectException(\InvalidArgumentException::class);
        app(IzinPegawaiService::class)->ajukan($e, [
            'jenis' => 'bolos_masal', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2026-07-01',
        ]);
    }

    // ---------------------------------------------------------------
    // (e) Non-approver tak bisa setujui
    // ---------------------------------------------------------------

    public function test_non_approver_tidak_bisa_setujui(): void
    {
        $this->boot('izn6');
        $kepala = $this->buatUser('Kepala');
        $e = $this->buatPegawai('Ust Fulan');
        $this->tugaskanPrimary($e, $kepala);
        $penyusup = $this->buatUser('Guru Lain');

        $svc = app(IzinPegawaiService::class);
        $izin = $svc->ajukan($e, [
            'jenis' => 'dinas', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2026-07-01',
        ], $e->users_id);

        $this->expectException(AuthorizationException::class);
        $svc->setujui($izin, $penyusup);
    }

    public function test_approver_bisa_setujui_dan_tolak(): void
    {
        $this->boot('izn7');
        $kepala = $this->buatUser('Kepala');
        $e = $this->buatPegawai('Ust A');
        $this->tugaskanPrimary($e, $kepala);
        $svc = app(IzinPegawaiService::class);

        $izin = $svc->ajukan($e, [
            'jenis' => 'keperluan_keluarga', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2026-07-01',
        ], $e->users_id);
        $izin = $svc->setujui($izin, $kepala);
        $this->assertSame('disetujui', $izin->status);
        $this->assertSame($kepala->id, $izin->disetujui_oleh);

        $izin2 = $svc->ajukan($e, [
            'jenis' => 'dinas', 'tanggal_mulai' => '2026-07-10', 'tanggal_selesai' => '2026-07-10',
        ], $e->users_id);
        $izin2 = $svc->tolak($izin2, $kepala, 'Jadwal padat');
        $this->assertSame('ditolak', $izin2->status);
    }

    // ---------------------------------------------------------------
    // (f) PWA Guru HTTP — smoke + gate 403
    // ---------------------------------------------------------------

    public function test_pwa_guru_izin_smoke_dan_gate_non_approver_403(): void
    {
        $tenant = $this->boot('izn8');
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $kepala = $this->buatUser('Kepala');
        $e = $this->buatPegawai('Ust PWA');
        $this->tugaskanPrimary($e, $kepala);

        $guru = $e->user;
        $guru->givePermissionTo('akses_pwa_guru');
        Auth::login($guru->fresh());

        // GET form/list izin saya → 200 Inertia.
        $res = $this->hit($tenant, 'GET', '/guru/izin');
        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString('PortalGuru\/Izin', $res->getContent());

        // POST ajukan → 302 + tersimpan (idempoten di-retry).
        $ref = (string) str()->uuid();
        $payload = [
            'jenis' => 'pulang_kampung', 'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2026-07-02', 'alasan' => 'Mudik', 'client_ref' => $ref,
        ];
        $this->assertSame(302, $this->hit($tenant, 'POST', '/guru/izin', $payload)->getStatusCode());
        $this->assertSame(302, $this->hit($tenant, 'POST', '/guru/izin', $payload)->getStatusCode());
        $this->assertSame(1, Cuti::query()->count(), 'Retry client_ref sama tidak dobel');

        $izin = Cuti::query()->firstOrFail();

        // Non-approver (pegawai sendiri) coba setujui → 403.
        $res = $this->hit($tenant, 'POST', "/guru/izin/{$izin->id}/setujui");
        $this->assertSame(403, $res->getStatusCode());
        $this->assertSame('diajukan', $izin->fresh()->status);

        // Approver (kepala) setujui dari PWA → 302 + disetujui.
        $kepala->givePermissionTo('akses_pwa_guru');
        Auth::login($kepala->fresh());
        $res = $this->hit($tenant, 'POST', "/guru/izin/{$izin->id}/setujui");
        $this->assertSame(302, $res->getStatusCode());
        $this->assertSame('disetujui', $izin->fresh()->status);
    }

    private function hit(Tenant $tenant, string $method, string $uri, array $payload = [])
    {
        $host = $tenant->domains()->first()->domain;
        $request = Request::create("http://{$host}{$uri}", $method, $payload);
        $request->headers->set('Host', $host);

        $response = app(Kernel::class)->handle($request);

        if (! tenancy()->initialized) {
            tenancy()->initialize($tenant);
        }

        return $response;
    }
}
