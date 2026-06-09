<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\PembayaranService;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Invarian I-1 (#23) — integritas uang end-to-end (agregat).
 *
 * Melengkapi LedgerAutoJurnalTest (1 pembayaran → 1 jurnal seimbang, idempoten)
 * dengan pembuktian AGREGAT lintas banyak pembayaran sesuai spesifikasi audit:
 *   Σ pembayaran verified = Δ saldo kas Ledger, 1:1 (nol orphan, nol ganda).
 */
class IntegritasUangTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('jurnal_baris')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    public function test_setiap_pembayaran_verified_punya_tepat_satu_jurnal_seimbang(): void
    {
        $tenant = $this->createRuntimeTenant('integ', 'Pesantren Integritas');
        tenancy()->initialize($tenant);
        (new LedgerSeeder)->run();
        $this->seedTenantRow($tenant);

        $verifier = User::withoutGlobalScopes()->create([
            'name' => 'Bendahara',
            'email' => 'bendahara@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);

        $santri = Santri::create([
            'nis' => 'INT-'.str()->random(5),
            'nama' => 'Santri Integritas',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
        ]);

        $tagihan = Tagihan::create([
            'santri_id' => $santri->id,
            'jenis_tagihan' => 'spp',
            'nominal' => 300000,
            'nominal_akhir' => 300000,
            'jatuh_tempo' => '2026-06-30',
            'status' => 'belum_bayar',
        ]);

        $svc = app(PembayaranService::class);

        // Tiga cicilan terverifikasi → tagihan lunas. Tiap verifikasi → 1 jurnal.
        foreach ([100000, 100000, 100000] as $nominal) {
            $bayar = $svc->inputPembayaran($tagihan->fresh(), ['nominal' => $nominal]);
            $svc->verifikasiPembayaran($bayar, $verifier->id);
        }

        $tagihan->refresh();
        $this->assertSame('lunas', $tagihan->status);

        $verifiedCount = $tagihan->pembayaran()->where('is_verified', true)->count();
        $this->assertSame(3, $verifiedCount);

        $jurnals = Jurnal::query()->where('ref_type', 'Pembayaran')->get();

        // (1) 1:1 — nol orphan, nol ganda.
        $this->assertSame(
            $verifiedCount,
            $jurnals->count(),
            'Jumlah jurnal harus sama dengan jumlah pembayaran verified (1:1).'
        );

        // (2) Tiap jurnal seimbang (debit = kredit).
        $totalDebit = 0.0;
        foreach ($jurnals as $jurnal) {
            $debit = (float) $jurnal->baris()->sum('debit');
            $kredit = (float) $jurnal->baris()->sum('kredit');
            $this->assertEqualsWithDelta($debit, $kredit, 0.01, "Jurnal #{$jurnal->id} harus seimbang.");
            $totalDebit += $debit;
        }

        // (3) Σ pembayaran verified = Σ jurnal (kontinuitas posting).
        $this->assertEqualsWithDelta(300000, $totalDebit, 0.01);

        // (4) Δ saldo kas = Σ pembayaran (debit ke akun kas/bank).
        $akunKas = Akun::kasBank()->where('is_postable', true)->orderBy('kode')->first();
        $this->assertNotNull($akunKas, 'COA harus punya akun kas/bank postable.');

        $totalKasDebit = 0.0;
        foreach ($jurnals as $jurnal) {
            $totalKasDebit += (float) $jurnal->baris()->where('akun_id', $akunKas->id)->sum('debit');
        }
        $this->assertEqualsWithDelta(300000, $totalKasDebit, 0.01, 'Δ saldo kas harus sama dengan total pembayaran verified.');

        // (5) Nol orphan: tiap jurnal pembayaran menunjuk pembayaran verified yang ADA.
        $verifiedIds = $tagihan->pembayaran()->where('is_verified', true)->pluck('id')->map(fn ($i) => (string) $i)->all();
        foreach ($jurnals as $jurnal) {
            $this->assertContains($jurnal->ref_id, $verifiedIds, 'Jurnal pembayaran tidak boleh orphan.');
        }
    }
}
