<?php

namespace Tests\Feature;

use App\Models\User;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\PinjamanPegawai;
use Modules\Payroll\Models\SlipGaji;
use Modules\Payroll\Services\KasbonService;
use Modules\Payroll\Services\PayrollService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-23 — Kasbon Pegawai (MONEY WP).
 *
 * (1) Total cicilan = pokok PERSIS (1jt / 300rb → 300+300+300+100, tanpa selisih).
 * (2) Pencairan idempoten: 2× = 1 jurnal, ref non-null, seimbang.
 * (3) Finalize 2× → sisa_pokok berkurang 1×.
 * (4) Cicilan > sisa neto → kasbon DIKAPS, neto tak pernah negatif, sisanya
 *     tertunda ke bulan berikut (sisa_pokok hanya berkurang sebesar terpotong).
 * (5) Lunas otomatis saat sisa 0.
 * (6) Pelunasan dipercepat: D Kas / K Piutang, validasi > sisa ditolak.
 * (7) Jurnal SEMUA jalur seimbang + idempotency_key non-null.
 * (8) TOCTOU: kasbon basi saat finalisasi → ditolak.
 */
class KasbonTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'sumber_dana', 'periode_akuntansi',
            'slip_gaji', 'employee', 'komponen_gaji', 'settings', 'cuti',
            'kalender_akademik', 'pinjaman_pegawai', 'approval_rules', 'approval_requests',
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

    private function buatEmployee(): Employee
    {
        $u = User::withoutGlobalScopes()->create([
            'name' => 'Ustadz', 'email' => 'u'.str()->random(6).'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);

        return Employee::create(['users_id' => $u->id, 'nama' => 'Ustadz', 'ptkp_status' => 'TK0', 'aktif' => true]);
    }

    /** Ajukan + setujui + cairkan → status berjalan. */
    private function kasbonBerjalan(Employee $e, float $nominal, float $cicilan): PinjamanPegawai
    {
        $svc = app(KasbonService::class);
        $p = $svc->ajukan($e->id, $nominal, $cicilan);
        $p = $svc->setujui($p);

        return $svc->cairkan($p);
    }

    private function slipFinal(Employee $e, string $periode, float $honor): SlipGaji
    {
        $svc = app(PayrollService::class);
        $slip = $svc->buatSlip(['users_id' => $e->users_id, 'periode' => $periode, 'honor_pokok' => $honor]);
        $slip = $svc->terapkanPotongan($slip, $e);

        return $svc->finalisasi($slip->fresh());
    }

    private function assertJurnalSeimbangDanBerkunci(?int $jurnalId): Jurnal
    {
        $this->assertNotNull($jurnalId);
        $j = Jurnal::with('baris')->findOrFail($jurnalId);
        $this->assertNotNull($j->idempotency_key, 'Jurnal uang wajib berkunci idempotensi');
        $this->assertEqualsWithDelta((float) $j->baris->sum('debit'), (float) $j->baris->sum('kredit'), 0.01);

        return $j;
    }

    // ------------------------------------------------------------------
    // (1)+(5) Total cicilan = pokok persis; lunas otomatis saat sisa 0
    // ------------------------------------------------------------------

    public function test_total_cicilan_sama_dengan_pokok_persis_dan_lunas_otomatis(): void
    {
        $this->boot('kb1');
        $e = $this->buatEmployee();
        $p = $this->kasbonBerjalan($e, 1000000, 300000);
        $this->assertSame('berjalan', $p->status);

        $potongan = [];
        foreach (['2026-06', '2026-07', '2026-08', '2026-09'] as $periode) {
            $slip = $this->slipFinal($e, $periode, 2600000);
            $potongan[] = (float) $slip->potongan_kasbon;
            $this->assertJurnalSeimbangDanBerkunci($slip->jurnal_id);
        }

        // Cicilan terakhir = sisa (100rb), BUKAN cicilan penuh — total = pokok PERSIS.
        $this->assertSame([300000.0, 300000.0, 300000.0, 100000.0], $potongan);
        $this->assertEqualsWithDelta(1000000, array_sum($potongan), 0.001);

        $p = $p->fresh();
        $this->assertEqualsWithDelta(0, (float) $p->sisa_pokok, 0.001);
        $this->assertSame('lunas', $p->status);

        // Bulan berikutnya: pinjaman lunas → tak memotong lagi.
        $slip5 = $this->slipFinal($e, '2026-10', 2600000);
        $this->assertEqualsWithDelta(0, (float) $slip5->potongan_kasbon, 0.001);
        tenancy()->end();
    }

    // ------------------------------------------------------------------
    // (2) Pencairan idempoten + guard status
    // ------------------------------------------------------------------

    public function test_pencairan_dua_kali_hanya_satu_jurnal(): void
    {
        $this->boot('kb2');
        $e = $this->buatEmployee();
        $svc = app(KasbonService::class);
        $p = $svc->setujui($svc->ajukan($e->id, 500000, 100000));

        $p1 = $svc->cairkan($p);
        $p2 = $svc->cairkan($p1->fresh()); // klik kedua

        $this->assertSame($p1->jurnal_pencairan_id, $p2->jurnal_pencairan_id);
        $this->assertSame(1, Jurnal::where('ref_type', 'PinjamanPegawai')->where('ref_id', (string) $p->id)->count());

        $j = $this->assertJurnalSeimbangDanBerkunci($p1->jurnal_pencairan_id);
        // D Piutang Pegawai (1-1400) / K Kas.
        $piutang = Akun::where('kode', KasbonService::KODE_AKUN_PIUTANG_PEGAWAI)->firstOrFail();
        $this->assertEqualsWithDelta(500000, (float) $j->baris->where('akun_id', $piutang->id)->sum('debit'), 0.001);
        tenancy()->end();
    }

    public function test_pencairan_sebelum_disetujui_ditolak(): void
    {
        $this->boot('kb3');
        $e = $this->buatEmployee();
        $svc = app(KasbonService::class);
        $p = $svc->ajukan($e->id, 500000, 100000);

        $this->expectExceptionMessage('berstatus disetujui');
        $svc->cairkan($p);
    }

    // ------------------------------------------------------------------
    // (3) Finalize 2× → sisa berkurang 1×
    // ------------------------------------------------------------------

    public function test_finalisasi_dua_kali_sisa_pokok_berkurang_sekali(): void
    {
        $this->boot('kb4');
        $e = $this->buatEmployee();
        $p = $this->kasbonBerjalan($e, 1000000, 300000);

        $svc = app(PayrollService::class);
        $slip = $svc->buatSlip(['users_id' => $e->users_id, 'periode' => '2026-06', 'honor_pokok' => 2600000]);
        $slip = $svc->terapkanPotongan($slip, $e);
        $slip = $svc->finalisasi($slip->fresh());
        $slip2 = $svc->finalisasi($slip->fresh()); // panggilan kedua — no-op

        $this->assertSame($slip->jurnal_id, $slip2->jurnal_id);
        $this->assertEqualsWithDelta(700000, (float) $p->fresh()->sisa_pokok, 0.001, 'Berkurang 300rb SEKALI');
        tenancy()->end();
    }

    // ------------------------------------------------------------------
    // (4) Neto tak boleh negatif — kasbon dikaps, sisanya tertunda
    // ------------------------------------------------------------------

    public function test_cicilan_melebihi_neto_dikaps_neto_nol_sisa_tertunda(): void
    {
        $this->boot('kb5');
        $e = $this->buatEmployee();
        $p = $this->kasbonBerjalan($e, 1000000, 600000);

        // Honor 500rb → BPJS 4% = 20rb → sisa neto 480rb < cicilan 600rb.
        $slip = $this->slipFinal($e, '2026-06', 500000);
        $this->assertEqualsWithDelta(480000, (float) $slip->potongan_kasbon, 0.001);
        $this->assertEqualsWithDelta(0, (float) $slip->neto, 0.001);
        $this->assertGreaterThanOrEqual(0, (float) $slip->neto);

        // sisa_pokok hanya berkurang sebesar yang TERPOTONG (480rb) — sisanya
        // tertunda ke bulan berikut.
        $this->assertEqualsWithDelta(520000, (float) $p->fresh()->sisa_pokok, 0.001);
        $this->assertSame('berjalan', $p->fresh()->status);
        $this->assertJurnalSeimbangDanBerkunci($slip->jurnal_id);
        tenancy()->end();
    }

    // ------------------------------------------------------------------
    // (6)+(7) Pelunasan dipercepat
    // ------------------------------------------------------------------

    public function test_pelunasan_dipercepat_jurnal_seimbang_dan_lunas(): void
    {
        $this->boot('kb6');
        $e = $this->buatEmployee();
        $p = $this->kasbonBerjalan($e, 1000000, 300000);
        $svc = app(KasbonService::class);

        $p = $svc->lunasi($p, 400000);
        $this->assertEqualsWithDelta(600000, (float) $p->sisa_pokok, 0.001);
        $this->assertSame('berjalan', $p->status);

        $p = $svc->lunasi($p, 600000);
        $this->assertEqualsWithDelta(0, (float) $p->sisa_pokok, 0.001);
        $this->assertSame('lunas', $p->status);

        // Dua jurnal pelunasan: D Kas / K Piutang, seimbang, kunci non-null.
        $jurnals = Jurnal::where('ref_type', 'PinjamanPegawaiPelunasan')->get();
        $this->assertCount(2, $jurnals);
        $piutang = Akun::where('kode', KasbonService::KODE_AKUN_PIUTANG_PEGAWAI)->firstOrFail();
        foreach ($jurnals as $j) {
            $this->assertJurnalSeimbangDanBerkunci($j->id);
            $j->load('baris');
            $this->assertGreaterThan(0, (float) $j->baris->where('akun_id', $piutang->id)->sum('kredit'));
        }

        // Saldo piutang pegawai kembali 0: debit pencairan 1jt = kredit 400rb + 600rb.
        tenancy()->end();
    }

    public function test_pelunasan_melebihi_sisa_ditolak(): void
    {
        $this->boot('kb7');
        $e = $this->buatEmployee();
        $p = $this->kasbonBerjalan($e, 500000, 100000);

        $this->expectExceptionMessage('melebihi sisa pokok');
        app(KasbonService::class)->lunasi($p, 600000);
    }

    // ------------------------------------------------------------------
    // (8) TOCTOU — potongan kasbon basi saat finalisasi ditolak
    // ------------------------------------------------------------------

    public function test_finalisasi_dengan_potongan_kasbon_basi_ditolak(): void
    {
        $this->boot('kb8');
        $e = $this->buatEmployee();
        $p = $this->kasbonBerjalan($e, 300000, 300000);

        $svc = app(PayrollService::class);
        $slip = $svc->buatSlip(['users_id' => $e->users_id, 'periode' => '2026-06', 'honor_pokok' => 2600000]);
        $slip = $svc->terapkanPotongan($slip, $e); // potongan_kasbon = 300rb

        // Di antara draft & finalisasi: dilunasi dipercepat → sisa 0.
        app(KasbonService::class)->lunasi($p, 300000);

        $this->expectExceptionMessage('Potongan kasbon slip basi');
        $svc->finalisasi($slip->fresh());
    }

    // ------------------------------------------------------------------
    // Snapshot regresi: tanpa kasbon, slip & jurnal identik baseline WP-14
    // ------------------------------------------------------------------

    public function test_tanpa_kasbon_slip_dan_jurnal_tak_berubah(): void
    {
        $this->boot('kb9');
        $e = $this->buatEmployee();

        $slip = $this->slipFinal($e, '2026-06', 10000000);
        $this->assertEqualsWithDelta(250000, (float) $slip->pph21_dipotong, 0.01);
        $this->assertEqualsWithDelta(400000, (float) $slip->bpjs_dipotong, 0.01);
        $this->assertEqualsWithDelta(0, (float) $slip->potongan_kasbon, 0.01);
        $this->assertEqualsWithDelta(9350000, (float) $slip->neto, 0.01);

        $j = $this->assertJurnalSeimbangDanBerkunci($slip->jurnal_id);
        $this->assertCount(4, $j->baris, 'Tanpa kasbon: baris jurnal identik baseline (bruto, pph, bpjs, neto)');
        tenancy()->end();
    }
}
