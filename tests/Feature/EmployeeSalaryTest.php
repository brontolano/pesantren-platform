<?php

namespace Tests\Feature;

use App\Models\User;
use InvalidArgumentException;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Services\PayrollService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G2-10 — Employee Master + Salary Structure. Menguji resolusi struktur gaji,
 * buatSlipUntukEmployee (delegasi ke buatSlip), guard tanpa user, idempoten, isolasi.
 */
class EmployeeSalaryTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'sumber_dana', 'periode_akuntansi',
            'slip_gaji', 'employee', 'komponen_gaji', 'jurnal', 'jurnal_baris',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
    }

    private function user(): User
    {
        return User::withoutGlobalScopes()->create([
            'name' => 'Ustadz', 'email' => 'u'.str()->random(5).'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);
    }

    private function employeeDenganStruktur(?int $usersId): Employee
    {
        $e = Employee::create([
            'users_id' => $usersId, 'nama' => 'Ustadz Fulan', 'jabatan' => 'Guru',
            'status_kepegawaian' => 'tetap', 'ptkp_status' => 'K1', 'aktif' => true,
        ]);
        $e->komponenGaji()->createMany([
            ['nama' => 'Honor Pokok', 'tipe' => 'tetap', 'nominal' => 2000000, 'aktif' => true],
            ['nama' => 'Tunjangan Jabatan', 'tipe' => 'tunjangan', 'nominal' => 500000, 'aktif' => true],
            ['nama' => 'Potongan Koperasi', 'tipe' => 'potongan', 'nominal' => 100000, 'aktif' => true],
        ]);

        return $e->fresh('komponenGaji');
    }

    public function test_struktur_gaji_terjumlah_per_tipe(): void
    {
        $this->boot('emp1');
        $s = $this->employeeDenganStruktur($this->user()->id)->strukturGaji();
        $this->assertEqualsWithDelta(2000000, $s['honor_pokok'], 0.01);
        $this->assertEqualsWithDelta(500000, $s['tunjangan'], 0.01);
        $this->assertEqualsWithDelta(100000, $s['potongan'], 0.01);
        tenancy()->end();
    }

    public function test_buat_slip_dari_struktur(): void
    {
        $this->boot('emp2');
        $e = $this->employeeDenganStruktur($this->user()->id);
        $slip = app(PayrollService::class)->buatSlipUntukEmployee($e, '2026-06');

        $this->assertEqualsWithDelta(2000000, (float) $slip->honor_pokok, 0.01);
        $this->assertEqualsWithDelta(500000, (float) $slip->tunjangan, 0.01);
        $this->assertEqualsWithDelta(2500000, (float) $slip->bruto, 0.01); // pokok + tunjangan
        $this->assertSame('draft', $slip->status);
        tenancy()->end();
    }

    public function test_employee_tanpa_user_ditolak(): void
    {
        $this->boot('emp3');
        $e = $this->employeeDenganStruktur(null);
        $this->expectException(InvalidArgumentException::class);
        app(PayrollService::class)->buatSlipUntukEmployee($e, '2026-06');
        tenancy()->end();
    }

    public function test_idempoten_per_periode(): void
    {
        $this->boot('emp4');
        $e = $this->employeeDenganStruktur($this->user()->id);
        $svc = app(PayrollService::class);
        $a = $svc->buatSlipUntukEmployee($e, '2026-06');
        $b = $svc->buatSlipUntukEmployee($e, '2026-06');
        $this->assertSame($a->id, $b->id);
        tenancy()->end();
    }

    public function test_isolasi_tenant(): void
    {
        $this->boot('empA');
        $this->employeeDenganStruktur($this->user()->id);
        $this->assertSame(1, Employee::query()->count());
        tenancy()->end();

        $this->boot('empB');
        $this->assertSame(0, Employee::query()->count());
        tenancy()->end();
    }
}
