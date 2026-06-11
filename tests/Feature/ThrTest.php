<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Jurnal;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\KomponenGaji;
use Modules\Payroll\Models\SlipGaji;
use Modules\Payroll\Services\PayrollService;
use Modules\Payroll\Services\ThrService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-15 — THR (MONEY WP).
 *
 * (a) Idempoten: generate 2× tidak dobel.
 * (b) Pegawai non-aktif tidak kebagian.
 * (c) Formula nominal_tetap vs kali_honor_pokok vs manual → angka EKSAK.
 * (d) Policy-first: 2 tenant formula beda → hasil beda.
 * (e) Finalisasi slip THR via jalur existing → jurnal seimbang,
 *     idempotency_key terisi (SlipGaji:<id>), tidak bentrok slip bulanan
 *     pegawai sama, potongan_cuti tetap 0 (guard periode non-YYYY-MM).
 * (f) Migration aman utk slip existing: tipe backfill 'bulanan'.
 */
class ThrTest extends TestCase
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

    private function buatEmployee(string $nama, bool $aktif = true, float $honorPokok = 0): Employee
    {
        $u = User::withoutGlobalScopes()->create([
            'name' => $nama, 'email' => 'u'.str()->random(6).'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);
        $e = Employee::create(['users_id' => $u->id, 'nama' => $nama, 'ptkp_status' => 'TK0', 'aktif' => $aktif]);
        if ($honorPokok > 0) {
            KomponenGaji::create([
                'employee_id' => $e->id, 'nama' => 'Honor Pokok', 'tipe' => 'tetap',
                'nominal' => $honorPokok, 'aktif' => true,
            ]);
        }

        return $e;
    }

    // ---------------------------------------------------------------
    // (a) Idempoten — generate 2× tidak dobel + ringkasan benar
    // ---------------------------------------------------------------

    public function test_generate_dua_kali_tidak_dobel(): void
    {
        $this->boot('thr1');
        Setting::create(['key' => ThrService::SETTING_FORMULA, 'value' => ['value' => 'nominal_tetap']]);
        Setting::create(['key' => ThrService::SETTING_NOMINAL, 'value' => ['value' => 1500000]]);
        $this->buatEmployee('A');
        $this->buatEmployee('B');

        $svc = app(ThrService::class);
        $h1 = $svc->generateMassal(2026);
        $this->assertSame(2, $h1['dibuat']);
        $this->assertSame(0, $h1['dilewati']);

        $h2 = $svc->generateMassal(2026);
        $this->assertSame(0, $h2['dibuat']);
        $this->assertSame(2, $h2['dilewati']);

        $this->assertSame(2, SlipGaji::where('tipe', 'thr')->where('tahun_thr', 2026)->count());
        // Tahun berbeda BOLEH dapat THR lagi (unique per tahun, bukan selamanya).
        $h3 = $svc->generateMassal(2027);
        $this->assertSame(2, $h3['dibuat']);
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (b) Pegawai non-aktif tidak kebagian
    // ---------------------------------------------------------------

    public function test_pegawai_non_aktif_tidak_kebagian(): void
    {
        $this->boot('thr2');
        Setting::create(['key' => ThrService::SETTING_FORMULA, 'value' => ['value' => 'nominal_tetap']]);
        Setting::create(['key' => ThrService::SETTING_NOMINAL, 'value' => ['value' => 1000000]]);
        $aktif = $this->buatEmployee('Aktif');
        $nonAktif = $this->buatEmployee('NonAktif', aktif: false);

        $hasil = app(ThrService::class)->generateMassal(2026);
        $this->assertSame(1, $hasil['dibuat']);
        $this->assertTrue(SlipGaji::where('tipe', 'thr')->where('users_id', $aktif->users_id)->exists());
        $this->assertFalse(SlipGaji::where('tipe', 'thr')->where('users_id', $nonAktif->users_id)->exists());
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (c) Formula → angka EKSAK
    // ---------------------------------------------------------------

    public function test_formula_nominal_tetap_angka_eksak(): void
    {
        $this->boot('thr3');
        Setting::create(['key' => ThrService::SETTING_FORMULA, 'value' => ['value' => 'nominal_tetap']]);
        // Parsing toleran: string angka.
        Setting::create(['key' => ThrService::SETTING_NOMINAL, 'value' => ['value' => '2750000']]);
        $e = $this->buatEmployee('A', honorPokok: 9999999); // honor diabaikan oleh formula ini.

        app(ThrService::class)->generateMassal(2026);
        $slip = SlipGaji::where('tipe', 'thr')->where('users_id', $e->users_id)->firstOrFail();
        $this->assertSame('2750000.00', (string) $slip->bruto);
        $this->assertSame('2750000.00', (string) $slip->neto);
        $this->assertSame('0.00', (string) $slip->pph21_dipotong, 'THR draft TANPA PPh21 otomatis');
        $this->assertSame('draft', $slip->status);
        $this->assertSame('THR-2026', $slip->periode);
        tenancy()->end();
    }

    public function test_formula_kali_honor_pokok_angka_eksak(): void
    {
        $this->boot('thr4');
        Setting::create(['key' => ThrService::SETTING_FORMULA, 'value' => ['value' => 'kali_honor_pokok']]);
        Setting::create(['key' => ThrService::SETTING_PENGALI, 'value' => ['value' => 1.5]]);
        $e = $this->buatEmployee('A', honorPokok: 2600000);

        app(ThrService::class)->generateMassal(2026);
        $slip = SlipGaji::where('tipe', 'thr')->where('users_id', $e->users_id)->firstOrFail();
        $this->assertSame('3900000.00', (string) $slip->bruto); // 2.600.000 × 1,5 — eksak.
        tenancy()->end();
    }

    public function test_formula_kali_honor_pokok_pengali_default_satu(): void
    {
        $this->boot('thr5');
        Setting::create(['key' => ThrService::SETTING_FORMULA, 'value' => ['value' => 'kali_honor_pokok']]);
        $e = $this->buatEmployee('A', honorPokok: 2600000);

        app(ThrService::class)->generateMassal(2026);
        $slip = SlipGaji::where('tipe', 'thr')->where('users_id', $e->users_id)->firstOrFail();
        $this->assertSame('2600000.00', (string) $slip->bruto);
        tenancy()->end();
    }

    public function test_formula_manual_pakai_override_dan_tanpa_input_dilewati(): void
    {
        $this->boot('thr6');
        Setting::create(['key' => ThrService::SETTING_FORMULA, 'value' => ['value' => 'manual']]);
        $eA = $this->buatEmployee('A');
        $eB = $this->buatEmployee('B'); // tanpa input → nominal 0 → tidak dibuat.

        $hasil = app(ThrService::class)->generateMassal(2026, [$eA->id => 1234567]);
        $this->assertSame(1, $hasil['dibuat']);
        $this->assertSame(1, $hasil['nominal_nol']);

        $slip = SlipGaji::where('tipe', 'thr')->where('users_id', $eA->users_id)->firstOrFail();
        $this->assertSame('1234567.00', (string) $slip->bruto);
        $this->assertFalse(SlipGaji::where('tipe', 'thr')->where('users_id', $eB->users_id)->exists());
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (d) Policy-first — 2 tenant formula beda → hasil beda
    // ---------------------------------------------------------------

    public function test_dua_tenant_formula_beda_hasil_beda(): void
    {
        $this->boot('thr7a');
        Setting::create(['key' => ThrService::SETTING_FORMULA, 'value' => ['value' => 'nominal_tetap']]);
        Setting::create(['key' => ThrService::SETTING_NOMINAL, 'value' => ['value' => 1000000]]);
        $eA = $this->buatEmployee('A', honorPokok: 2600000);
        app(ThrService::class)->generateMassal(2026);
        $brutoA = (string) SlipGaji::where('tipe', 'thr')->where('users_id', $eA->users_id)->firstOrFail()->bruto;
        tenancy()->end();

        $this->boot('thr7b');
        Setting::create(['key' => ThrService::SETTING_FORMULA, 'value' => ['value' => 'kali_honor_pokok']]);
        Setting::create(['key' => ThrService::SETTING_PENGALI, 'value' => ['value' => 2]]);
        $eB = $this->buatEmployee('B', honorPokok: 2600000);
        app(ThrService::class)->generateMassal(2026);
        $brutoB = (string) SlipGaji::where('tipe', 'thr')->where('users_id', $eB->users_id)->firstOrFail()->bruto;

        $this->assertSame('1000000.00', $brutoA);
        $this->assertSame('5200000.00', $brutoB);
        $this->assertNotSame($brutoA, $brutoB);
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (e) Finalisasi via jalur existing — jurnal seimbang + idempoten +
    //     tak bentrok slip bulanan pegawai sama
    // ---------------------------------------------------------------

    public function test_finalisasi_slip_thr_jurnal_seimbang_idempotency_key_terisi(): void
    {
        $this->boot('thr8');
        Setting::create(['key' => ThrService::SETTING_FORMULA, 'value' => ['value' => 'nominal_tetap']]);
        Setting::create(['key' => ThrService::SETTING_NOMINAL, 'value' => ['value' => 1500000]]);
        $e = $this->buatEmployee('A', honorPokok: 2600000);

        $payroll = app(PayrollService::class);
        // Slip BULANAN pegawai sama, periode 2026-06 — harus tetap bisa hidup
        // berdampingan dengan slip THR (unique periode tak bentrok).
        $slipBulanan = $payroll->buatSlip(['users_id' => $e->users_id, 'periode' => '2026-06', 'honor_pokok' => 2600000]);

        app(ThrService::class)->generateMassal(2026);
        $slipThr = SlipGaji::where('tipe', 'thr')->where('users_id', $e->users_id)->firstOrFail();
        $this->assertSame('0.00', (string) $slipThr->potongan_cuti, 'Guard periode non-YYYY-MM → potongan cuti 0');

        // Finalisasi via jalur EXISTING (tidak ada jalur posting baru).
        $slipThr = $payroll->finalisasi($slipThr);
        $this->assertSame('final', $slipThr->status);

        $jurnal = Jurnal::with('baris')->find($slipThr->jurnal_id);
        $this->assertNotNull($jurnal);
        $this->assertSame('SlipGaji:'.$slipThr->id, $jurnal->idempotency_key);
        $this->assertEqualsWithDelta($jurnal->baris->sum('debit'), $jurnal->baris->sum('kredit'), 0.001);
        $this->assertEqualsWithDelta(1500000, (float) $jurnal->baris->sum('debit'), 0.001);

        // Finalisasi ulang → jurnal sama (idempoten), tidak dobel posting.
        $jurnalId = $slipThr->jurnal_id;
        $slipThr = $payroll->finalisasi($slipThr->fresh());
        $this->assertSame($jurnalId, $slipThr->jurnal_id);
        $this->assertSame(1, Jurnal::where('idempotency_key', 'SlipGaji:'.$slipThr->id)->count());

        // Slip bulanan pegawai sama tetap utuh & terfinalisasi terpisah.
        $slipBulanan = $payroll->finalisasi($slipBulanan->fresh());
        $this->assertNotSame($slipThr->jurnal_id, $slipBulanan->jurnal_id);
        $this->assertSame('bulanan', $slipBulanan->fresh()->tipe);
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (f) Migration aman — slip existing ter-backfill tipe 'bulanan'
    // ---------------------------------------------------------------

    public function test_slip_existing_backfill_tipe_bulanan(): void
    {
        $this->boot('thr9');
        $e = $this->buatEmployee('A');
        // Slip dibuat via jalur lama TANPA menyebut 'tipe' → DEFAULT 'bulanan'.
        $slip = app(PayrollService::class)->buatSlip([
            'users_id' => $e->users_id, 'periode' => '2026-06', 'honor_pokok' => 1000000,
        ]);

        $this->assertSame('bulanan', $slip->fresh()->tipe);
        $this->assertNull($slip->fresh()->tahun_thr);
        tenancy()->end();
    }
}
