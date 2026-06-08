<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Services\JurnalService;
use Modules\Ledger\Services\LaporanService;
use Modules\Payroll\Models\SlipGaji;
use Modules\Payroll\Services\PayrollService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PayrollDanaTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurePpdbTestingDatabase();
        if (! Schema::connection('pgsql')->hasTable('slip_gaji')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(): void
    {
        $tenant = $this->createRuntimeTenant('pay', 'Pesantren Payroll');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
    }

    private function ptk(): User
    {
        return User::withoutGlobalScopes()->create([
            'name' => 'Ustadz', 'email' => 'u' . str()->random(4) . '@' . tenant('id') . '.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);
    }

    public function test_slip_gaji_neto_dan_jurnal_seimbang(): void
    {
        $this->boot();
        $ptk = $this->ptk();

        // Pokok 2jt + tunjangan 500rb + 20 poin × 10.000 (default) + output 300rb = 3.000.000
        $slip = app(PayrollService::class)->buatSlip([
            'users_id' => $ptk->id, 'periode' => '2026-06',
            'honor_pokok' => 2000000, 'tunjangan' => 500000, 'poin' => 20, 'honor_output' => 300000,
        ]);

        $this->assertEqualsWithDelta(200000, (float) $slip->honor_poin, 0.01); // 20 × 10rb
        $this->assertEqualsWithDelta(3000000, (float) $slip->bruto, 0.01);
        $this->assertEqualsWithDelta(0, (float) $slip->pph21_dipotong, 0.01);   // default 0%
        $this->assertEqualsWithDelta(3000000, (float) $slip->neto, 0.01);

        $slip = app(PayrollService::class)->finalisasi($slip);
        $this->assertSame('final', $slip->status);

        $jurnal = Jurnal::find($slip->jurnal_id);
        $this->assertEqualsWithDelta(
            (float) $jurnal->baris()->sum('debit'),
            (float) $jurnal->baris()->sum('kredit'),
            0.01,
            'Jurnal gaji harus seimbang.'
        );
        $this->assertEqualsWithDelta(3000000, (float) $jurnal->baris()->sum('debit'), 0.01);
    }

    public function test_slip_idempoten_per_periode(): void
    {
        $this->boot();
        $ptk = $this->ptk();
        $svc = app(PayrollService::class);
        $a = $svc->buatSlip(['users_id' => $ptk->id, 'periode' => '2026-06', 'honor_pokok' => 1000000]);
        $b = $svc->buatSlip(['users_id' => $ptk->id, 'periode' => '2026-06', 'honor_pokok' => 9999999]);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, SlipGaji::count());
    }

    public function test_dana_terikat_wakaf_dan_zis_terpisah(): void
    {
        $this->boot();
        $svc = app(JurnalService::class);
        // Penerimaan Wakaf (04) 5jt → akun 4-3000
        $svc->postJurnal([
            'tanggal' => '2026-06-02', 'ref_type' => 'T', 'ref_id' => 'wkf',
            'baris' => [
                ['akun_id' => \Modules\Ledger\Models\Akun::where('kode', '1-1000')->value('id'), 'sumber_dana_id' => \Modules\Ledger\Models\SumberDana::where('kode_dana', '04')->value('id'), 'debit' => 5000000],
                ['akun_id' => \Modules\Ledger\Models\Akun::where('kode', '4-3000')->value('id'), 'sumber_dana_id' => \Modules\Ledger\Models\SumberDana::where('kode_dana', '04')->value('id'), 'kredit' => 5000000],
            ],
        ]);
        // Penerimaan Zakat (05) 1jt → akun 4-4000
        $svc->postJurnal([
            'tanggal' => '2026-06-03', 'ref_type' => 'T', 'ref_id' => 'zkt',
            'baris' => [
                ['akun_id' => \Modules\Ledger\Models\Akun::where('kode', '1-1000')->value('id'), 'sumber_dana_id' => \Modules\Ledger\Models\SumberDana::where('kode_dana', '05')->value('id'), 'debit' => 1000000],
                ['akun_id' => \Modules\Ledger\Models\Akun::where('kode', '4-4000')->value('id'), 'sumber_dana_id' => \Modules\Ledger\Models\SumberDana::where('kode_dana', '05')->value('id'), 'kredit' => 1000000],
            ],
        ]);

        $dana = app(LaporanService::class)->laporanDanaTerikat();
        $this->assertCount(1, $dana['wakaf']);
        $this->assertEqualsWithDelta(5000000, $dana['wakaf'][0]['penerimaan'], 0.01);
        $this->assertCount(1, $dana['zis']);
        $this->assertEqualsWithDelta(1000000, $dana['zis'][0]['penerimaan'], 0.01);
    }
}
