<?php

namespace Tests\Feature;

use App\Models\User;
use Modules\Core\Exceptions\PersetujuanBelumLengkapException;
use Modules\Core\Models\ApprovalRule;
use Modules\Core\Services\ApprovalService;
use Modules\Payroll\Models\Cuti;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Services\CutiService;
use Modules\Payroll\Services\PresensiStafService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G2-11 — Attendance + Leave. Menguji rekap presensi, ajukan/setujui cuti
 * (gate F1), idempotensi presensi, dan isolasi.
 */
class AttendanceLeaveTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'employee', 'presensi_staf', 'khidmah',
            'cuti', 'approval_rule', 'approval_request',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): Employee
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->userId = (int) User::withoutGlobalScopes()->create([
            'name' => 'Admin', 'email' => 'a'.str()->random(5).'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ])->id;

        return Employee::create(['nama' => 'Ustadz', 'status_kepegawaian' => 'tetap', 'aktif' => true]);
    }

    public function test_presensi_rekap_dan_idempoten(): void
    {
        $e = $this->boot('att1');
        $svc = app(PresensiStafService::class);
        $svc->catat($e->id, '2026-06-01', 'hadir');
        $svc->catat($e->id, '2026-06-01', 'hadir'); // upsert → tetap 1 baris
        $svc->catat($e->id, '2026-06-02', 'hadir');
        $svc->catat($e->id, '2026-06-03', 'izin');
        $svc->catat($e->id, '2026-06-04', 'sakit');

        $r = $svc->rekapBulanan($e->id, '2026-06');
        $this->assertSame(2, $r['hadir']);
        $this->assertSame(1, $r['izin']);
        $this->assertSame(1, $r['sakit']);
        $this->assertSame(4, $r['total']);
        tenancy()->end();
    }

    public function test_cuti_ajukan_hitung_hari_inklusif(): void
    {
        $e = $this->boot('att2');
        $cuti = app(CutiService::class)->ajukan($e->id, 'tahunan', '2026-06-01', '2026-06-03', 'Mudik', $this->userId);
        $this->assertSame(3, $cuti->jumlah_hari);
        $this->assertSame('diajukan', $cuti->status);
        tenancy()->end();
    }

    public function test_cuti_setujui_tanpa_rule(): void
    {
        $e = $this->boot('att3');
        $svc = app(CutiService::class);
        $cuti = $svc->ajukan($e->id, 'tahunan', '2026-06-01', '2026-06-02', null, $this->userId);
        $cuti = $svc->setujui($cuti, $this->userId);
        $this->assertSame('disetujui', $cuti->status);
        $this->assertNotNull($cuti->disetujui_pada);
        tenancy()->end();
    }

    public function test_cuti_panjang_dengan_rule_wajib_terblokir_lalu_lolos(): void
    {
        $e = $this->boot('att4');
        ApprovalRule::create(['approvable_type' => Cuti::class, 'nama' => 'Cuti panjang',
            'field' => 'jumlah_hari', 'operator' => '>=', 'nilai' => 5, 'approver_role' => 'admin_pesantren',
            'mode' => 'wajib', 'urutan' => 1, 'aktif' => true]);
        $svc = app(CutiService::class);
        $cuti = $svc->ajukan($e->id, 'tahunan', '2026-06-01', '2026-06-07', null, $this->userId); // 7 hari

        try {
            $svc->setujui($cuti, $this->userId);
            $this->fail('Cuti panjang harus terblokir approval.');
        } catch (PersetujuanBelumLengkapException) {
            $this->assertSame('diajukan', $cuti->fresh()->status);
        }

        $req = $cuti->fresh()->approvalRequests()->where('status', 'menunggu')->latest('id')->first();
        app(ApprovalService::class)->setujui($req, $this->userId);
        $cuti = $svc->setujui($cuti->fresh(), $this->userId);
        $this->assertSame('disetujui', $cuti->status);
        tenancy()->end();
    }

    public function test_cuti_pendek_dengan_rule_tidak_tergate(): void
    {
        $e = $this->boot('att5');
        ApprovalRule::create(['approvable_type' => Cuti::class, 'nama' => 'Cuti panjang',
            'field' => 'jumlah_hari', 'operator' => '>=', 'nilai' => 5, 'approver_role' => 'admin_pesantren',
            'mode' => 'wajib', 'urutan' => 1, 'aktif' => true]);
        $svc = app(CutiService::class);
        $cuti = $svc->ajukan($e->id, 'tahunan', '2026-06-01', '2026-06-02', null, $this->userId); // 2 hari < 5
        $cuti = $svc->setujui($cuti, $this->userId);
        $this->assertSame('disetujui', $cuti->status);
        tenancy()->end();
    }

    public function test_isolasi_tenant(): void
    {
        $e = $this->boot('attA');
        app(CutiService::class)->ajukan($e->id, 'tahunan', '2026-06-01', '2026-06-02', null, $this->userId);
        $this->assertSame(1, Cuti::query()->count());
        tenancy()->end();

        $this->boot('attB');
        $this->assertSame(0, Cuti::query()->count());
        tenancy()->end();
    }
}
