<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Modules\Akademik\Models\KalenderAkademik;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Jurnal;
use Modules\Payroll\Models\Cuti;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\SlipGaji;
use Modules\Payroll\Services\PayrollService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-14 — Potongan cuti tak berbayar (MONEY WP).
 *
 * Konteks Juni 2026: 1 Juni = Senin; default libur mingguan = Minggu
 * (7, 14, 21, 28) → hari efektif tanpa entri kalender = 30 − 4 = 26.
 *
 * (a) SNAPSHOT: slip tanpa cuti & slip dgn cuti BERBAYAR identik dgn baseline
 *     (PPh21/BPJS/neto/jurnal tak berubah satu rupiah pun).
 * (b) Cuti tak berbayar N hari efektif → potongan = N × honor_pokok ÷ pembagi.
 * (c) Hari cuti jatuh di libur pondok / libur mingguan → TIDAK memotong.
 * (d) Cuti lintas dua periode → proporsional di masing-masing slip.
 * (e) 2 tenant pembagi beda (Setting) → potongan beda (policy-first).
 */
class PotonganCutiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'sumber_dana', 'periode_akuntansi',
            'slip_gaji', 'employee', 'komponen_gaji', 'settings', 'cuti', 'kalender_akademik',
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

    /** @return array{0: Employee, 1: SlipGaji} */
    private function buatEmployeeDanSlip(float $honorPokok, string $periode = '2026-06'): array
    {
        $u = User::withoutGlobalScopes()->create([
            'name' => 'Ustadz', 'email' => 'u'.str()->random(6).'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);
        $e = Employee::create(['users_id' => $u->id, 'nama' => 'Ustadz', 'ptkp_status' => 'TK0', 'aktif' => true]);
        $slip = app(PayrollService::class)->buatSlip([
            'users_id' => $u->id, 'periode' => $periode, 'honor_pokok' => $honorPokok,
        ]);

        return [$e, $slip];
    }

    private function cutiDisetujui(Employee $e, string $mulai, string $selesai, bool $berbayar): Cuti
    {
        return Cuti::create([
            'employee_id' => $e->id, 'jenis' => 'lainnya', 'is_berbayar' => $berbayar,
            'tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai,
            'jumlah_hari' => Carbon::parse($mulai)->diffInDays(Carbon::parse($selesai)) + 1,
            'status' => 'disetujui',
        ]);
    }

    // ---------------------------------------------------------------
    // (a) SNAPSHOT — cuti berbayar → potongan 0, slip & jurnal identik baseline
    // ---------------------------------------------------------------

    public function test_snapshot_cuti_berbayar_potongan_nol_slip_dan_jurnal_identik_baseline(): void
    {
        $this->boot('pc1');
        $svc = app(PayrollService::class);

        // Baseline: TANPA cuti sama sekali.
        [$eBase, $slipBase] = $this->buatEmployeeDanSlip(10000000);
        $slipBase = $svc->terapkanPotongan($slipBase, $eBase);

        // Dengan cuti BERBAYAR disetujui di bulan slip.
        [$eCuti, $slipCuti] = $this->buatEmployeeDanSlip(10000000);
        $this->cutiDisetujui($eCuti, '2026-06-08', '2026-06-12', true);
        $slipCuti = $svc->terapkanPotongan($slipCuti, $eCuti);

        // Math existing TIDAK berubah satu rupiah pun (regresi PPh21/BPJS).
        foreach (['bruto', 'pph21_dipotong', 'bpjs_dipotong', 'potongan_cuti', 'neto'] as $kolom) {
            $this->assertSame((string) $slipBase->{$kolom}, (string) $slipCuti->{$kolom}, "Kolom {$kolom} harus identik baseline");
        }
        $this->assertEqualsWithDelta(250000, (float) $slipCuti->pph21_dipotong, 0.01);
        $this->assertEqualsWithDelta(400000, (float) $slipCuti->bpjs_dipotong, 0.01);
        $this->assertEqualsWithDelta(0, (float) $slipCuti->potongan_cuti, 0.01);
        $this->assertEqualsWithDelta(9350000, (float) $slipCuti->neto, 0.01);

        // Jurnal final: jumlah baris & nominal identik baseline (posting tak berubah).
        $slipBase = $svc->finalisasi($slipBase->fresh());
        $slipCuti = $svc->finalisasi($slipCuti->fresh());
        $jBase = Jurnal::with('baris')->find($slipBase->jurnal_id);
        $jCuti = Jurnal::with('baris')->find($slipCuti->jurnal_id);
        $ringkas = fn ($j) => $j->baris->map(fn ($b) => [$b->akun_id, (string) $b->debit, (string) $b->kredit])->sortBy(fn ($r) => implode('|', $r))->values()->all();
        $this->assertSame($ringkas($jBase), $ringkas($jCuti));
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (b) Tak berbayar N hari efektif → N × honor_pokok ÷ pembagi
    // ---------------------------------------------------------------

    public function test_cuti_tak_berbayar_pembagi_tetap_potongan_eksak(): void
    {
        $this->boot('pc2');
        Setting::create(['key' => PayrollService::SETTING_HARI_KERJA_PEMBAGI, 'value' => ['value' => 26]]);

        // Honor 2,6jt ÷ 26 = 100rb/hari. Cuti Sen 1 — Rab 3 Juni = 3 hari efektif.
        [$e, $slip] = $this->buatEmployeeDanSlip(2600000);
        $this->cutiDisetujui($e, '2026-06-01', '2026-06-03', false);
        $slip = app(PayrollService::class)->terapkanPotongan($slip, $e);

        $this->assertEqualsWithDelta(300000, (float) $slip->potongan_cuti, 0.001);
        // Bruto 2,6jt < PTKP → PPh21 0; BPJS 4% = 104rb; neto = 2,6jt − 104rb − 300rb.
        $this->assertEqualsWithDelta(0, (float) $slip->pph21_dipotong, 0.01);
        $this->assertEqualsWithDelta(104000, (float) $slip->bpjs_dipotong, 0.01);
        $this->assertEqualsWithDelta(2196000, (float) $slip->neto, 0.01);
        tenancy()->end();
    }

    public function test_pembagi_default_kalender_efektif_dan_pembulatan_half_up(): void
    {
        $this->boot('pc3');
        // TANPA setting pembagi → kalender_efektif: Juni 2026 = 26 hari efektif.
        // Honor 1jt, 1 hari (Sen 1 Juni) → 1.000.000/26 = 38461,53.. → 38462 (half-up rupiah utuh).
        [$e, $slip] = $this->buatEmployeeDanSlip(1000000);
        $this->cutiDisetujui($e, '2026-06-01', '2026-06-01', false);
        $slip = app(PayrollService::class)->terapkanPotongan($slip, $e);

        $this->assertEqualsWithDelta(38462, (float) $slip->potongan_cuti, 0.001);
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (c) Hari cuti jatuh di libur pondok / mingguan → TIDAK memotong
    // ---------------------------------------------------------------

    public function test_hari_cuti_di_libur_pondok_dan_minggu_tidak_memotong(): void
    {
        $this->boot('pc4');
        Setting::create(['key' => PayrollService::SETTING_HARI_KERJA_PEMBAGI, 'value' => ['value' => 26]]);
        // Libur pondok Jum 5 — Sab 6 Juni.
        KalenderAkademik::create([
            'tanggal_mulai' => '2026-06-05', 'tanggal_selesai' => '2026-06-06',
            'jenis' => 'libur_pondok', 'keterangan' => 'Haul masyayikh',
        ]);

        // Cuti Jum 5 — Sen 8: 5-6 libur pondok, Minggu 7 libur mingguan → hanya Sen 8 memotong.
        [$e, $slip] = $this->buatEmployeeDanSlip(2600000);
        $this->cutiDisetujui($e, '2026-06-05', '2026-06-08', false);
        $slip = app(PayrollService::class)->terapkanPotongan($slip, $e);

        $this->assertEqualsWithDelta(100000, (float) $slip->potongan_cuti, 0.001);
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (d) Cuti lintas dua periode → proporsional di masing-masing slip
    // ---------------------------------------------------------------

    public function test_cuti_lintas_dua_periode_terpotong_proporsional(): void
    {
        $this->boot('pc5');
        Setting::create(['key' => PayrollService::SETTING_HARI_KERJA_PEMBAGI, 'value' => ['value' => 26]]);

        // Cuti Sen 29 Juni — Kam 2 Juli (4 hari kerja: 29, 30 Juni + 1, 2 Juli).
        [$e, $slipJuni] = $this->buatEmployeeDanSlip(2600000, '2026-06');
        $this->cutiDisetujui($e, '2026-06-29', '2026-07-02', false);
        $svc = app(PayrollService::class);

        $slipJuni = $svc->terapkanPotongan($slipJuni, $e);
        $this->assertEqualsWithDelta(200000, (float) $slipJuni->potongan_cuti, 0.001, 'Juni: hanya 29-30');

        $slipJuli = $svc->buatSlip(['users_id' => $e->users_id, 'periode' => '2026-07', 'honor_pokok' => 2600000]);
        $slipJuli = $svc->terapkanPotongan($slipJuli, $e);
        $this->assertEqualsWithDelta(200000, (float) $slipJuli->potongan_cuti, 0.001, 'Juli: hanya 1-2');
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (e) Policy-first: 2 tenant pembagi beda → potongan beda
    // ---------------------------------------------------------------

    public function test_dua_tenant_pembagi_beda_potongan_beda(): void
    {
        // Tenant A: pembagi 26 → 2 hari × 2,6jt/26 = 200.000.
        $this->boot('pc6a');
        Setting::create(['key' => PayrollService::SETTING_HARI_KERJA_PEMBAGI, 'value' => ['value' => 26]]);
        [$eA, $slipA] = $this->buatEmployeeDanSlip(2600000);
        $this->cutiDisetujui($eA, '2026-06-01', '2026-06-02', false);
        $slipA = app(PayrollService::class)->terapkanPotongan($slipA, $eA);
        $this->assertEqualsWithDelta(200000, (float) $slipA->potongan_cuti, 0.001);
        tenancy()->end();

        // Tenant B: pembagi 20 → 2 hari × 2,6jt/20 = 260.000 (kebijakan beda → hasil beda).
        $this->boot('pc6b');
        Setting::create(['key' => PayrollService::SETTING_HARI_KERJA_PEMBAGI, 'value' => ['value' => 20]]);
        [$eB, $slipB] = $this->buatEmployeeDanSlip(2600000);
        $this->cutiDisetujui($eB, '2026-06-01', '2026-06-02', false);
        $slipB = app(PayrollService::class)->terapkanPotongan($slipB, $eB);
        $this->assertEqualsWithDelta(260000, (float) $slipB->potongan_cuti, 0.001);

        $this->assertNotEquals((float) $slipA->potongan_cuti, (float) $slipB->potongan_cuti);
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // Pagar tambahan: jurnal slip DENGAN potongan cuti tetap seimbang
    // ---------------------------------------------------------------

    public function test_finalisasi_dengan_potongan_cuti_jurnal_seimbang(): void
    {
        $this->boot('pc7');
        Setting::create(['key' => PayrollService::SETTING_HARI_KERJA_PEMBAGI, 'value' => ['value' => 26]]);
        [$e, $slip] = $this->buatEmployeeDanSlip(2600000);
        $this->cutiDisetujui($e, '2026-06-01', '2026-06-03', false);
        $svc = app(PayrollService::class);
        $slip = $svc->terapkanPotongan($slip, $e);

        $slip = $svc->finalisasi($slip->fresh());
        $this->assertSame('final', $slip->status);
        $jurnal = Jurnal::with('baris')->find($slip->jurnal_id);
        $this->assertEqualsWithDelta($jurnal->baris->sum('debit'), $jurnal->baris->sum('kredit'), 0.01);
        $this->assertEqualsWithDelta(2600000, (float) $jurnal->baris->sum('debit'), 0.01);
        tenancy()->end();
    }
}
