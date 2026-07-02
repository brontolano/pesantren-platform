<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Jurnal;
use Modules\Pajak\Services\PajakPenghasilanService;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Services\BpjsService;
use Modules\Payroll\Services\PayrollService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G2-12 — PPh21 tiered (PTKP, UU HPP) + BPJS. Menguji PTKP, lapisan progresif,
 * pph21 bulanan, potongan BPJS, dan integrasi opt-in terapkanPotongan (neto).
 */
class PajakBpjsTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'sumber_dana', 'periode_akuntansi',
            'slip_gaji', 'employee', 'komponen_gaji', 'settings',
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

    public function test_ptkp_per_status(): void
    {
        $this->boot('ptkp1');
        $svc = app(PajakPenghasilanService::class);
        $this->assertEqualsWithDelta(54000000, $svc->ptkp('TK0'), 0.01);
        $this->assertEqualsWithDelta(63000000, $svc->ptkp('K1'), 0.01);
        $this->assertEqualsWithDelta(72000000, $svc->ptkp('K3'), 0.01);
        tenancy()->end();
    }

    public function test_pph21_tahunan_progresif_berlapis(): void
    {
        $this->boot('ptkp2');
        $svc = app(PajakPenghasilanService::class);
        $this->assertEqualsWithDelta(0, $svc->pph21Tahunan(0), 0.01);
        $this->assertEqualsWithDelta(3000000, $svc->pph21Tahunan(60000000), 0.01);      // 60jt × 5%
        $this->assertEqualsWithDelta(9000000, $svc->pph21Tahunan(100000000), 0.01);     // 3jt + 40jt×15%
        $this->assertEqualsWithDelta(44000000, $svc->pph21Tahunan(300000000), 0.01);    // 3jt + 28,5jt + 12,5jt
        tenancy()->end();
    }

    public function test_pph21_bulanan_di_bawah_ptkp_nol(): void
    {
        $this->boot('ptkp3');
        // Bruto 3jt/bln → setahun 36jt < PTKP 54jt → PPh21 = 0 (umum di pesantren).
        $this->assertEqualsWithDelta(0, app(PajakPenghasilanService::class)->pph21Bulanan(3000000, 'TK0'), 0.01);
        tenancy()->end();
    }

    public function test_pph21_bulanan_di_atas_ptkp(): void
    {
        $this->boot('ptkp4');
        // Bruto 10jt/bln, TK0: bruto thn 120jt − biaya jabatan 6jt = 114jt netto;
        // PKP = 114jt − 54jt = 60jt → pajak thn 3jt → bulanan 250rb.
        $this->assertEqualsWithDelta(250000, app(PajakPenghasilanService::class)->pph21Bulanan(10000000, 'TK0'), 0.01);
        tenancy()->end();
    }

    public function test_bpjs_potongan_pekerja(): void
    {
        $this->boot('bpjs1');
        // Default 1% + 2% + 1% = 4%.
        $this->assertEqualsWithDelta(400000, app(BpjsService::class)->potonganPekerja(10000000), 0.01);
        tenancy()->end();
    }

    public function test_terapkan_potongan_hitung_ulang_neto(): void
    {
        $this->boot('ptg1');
        $u = User::withoutGlobalScopes()->create([
            'name' => 'Ustadz', 'email' => 'u'.str()->random(5).'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);
        $e = Employee::create(['users_id' => $u->id, 'nama' => 'Ustadz', 'ptkp_status' => 'TK0', 'aktif' => true]);
        $svc = app(PayrollService::class);
        $slip = $svc->buatSlip(['users_id' => $u->id, 'periode' => '2026-06', 'honor_pokok' => 10000000]);
        $this->assertEqualsWithDelta(10000000, (float) $slip->bruto, 0.01);

        $slip = $svc->terapkanPotongan($slip, $e);
        $this->assertEqualsWithDelta(250000, (float) $slip->pph21_dipotong, 0.01);
        $this->assertEqualsWithDelta(400000, (float) $slip->bpjs_dipotong, 0.01);
        $this->assertEqualsWithDelta(9350000, (float) $slip->neto, 0.01); // 10jt − 250rb − 400rb
        tenancy()->end();
    }

    public function test_finalisasi_setelah_potongan_jurnal_seimbang(): void
    {
        $this->boot('ptg2');
        $u = User::withoutGlobalScopes()->create([
            'name' => 'Ustadz', 'email' => 'u'.str()->random(5).'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);
        $e = Employee::create(['users_id' => $u->id, 'nama' => 'Ustadz', 'ptkp_status' => 'TK0', 'aktif' => true]);
        $svc = app(PayrollService::class);
        $slip = $svc->buatSlip(['users_id' => $u->id, 'periode' => '2026-06', 'honor_pokok' => 10000000]);
        $svc->terapkanPotongan($slip, $e); // bpjs 400rb + pph21 250rb

        // Finalisasi HARUS posting jurnal seimbang (regresi cacat adversarial G2-12).
        $slip = $svc->finalisasi($slip->fresh());
        $this->assertSame('final', $slip->status);
        $this->assertNotNull($slip->jurnal_id);
        $jurnal = Jurnal::with('baris')->find($slip->jurnal_id);
        $this->assertEqualsWithDelta($jurnal->baris->sum('debit'), $jurnal->baris->sum('kredit'), 0.01);
        $this->assertEqualsWithDelta(10000000, (float) $jurnal->baris->sum('debit'), 0.01);
        tenancy()->end();
    }

    public function test_terapkan_potongan_neto_negatif_ditolak(): void
    {
        $this->boot('ptg3');
        // Paksa rate BPJS sangat besar agar potongan > bruto.
        Setting::create(['key' => 'keuangan.bpjs.kesehatan_pekerja', 'value' => ['value' => 200]]);
        $u = User::withoutGlobalScopes()->create([
            'name' => 'X', 'email' => 'x'.str()->random(5).'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);
        $e = Employee::create(['users_id' => $u->id, 'nama' => 'X', 'ptkp_status' => 'TK0', 'aktif' => true]);
        $svc = app(PayrollService::class);
        $slip = $svc->buatSlip(['users_id' => $u->id, 'periode' => '2026-06', 'honor_pokok' => 1000000]);

        $this->expectException(\InvalidArgumentException::class);
        $svc->terapkanPotongan($slip, $e);
        tenancy()->end();
    }
}
