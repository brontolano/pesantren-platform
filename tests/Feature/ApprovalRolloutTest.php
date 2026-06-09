<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Exceptions\PersetujuanBelumLengkapException;
use Modules\Core\Models\ApprovalRule;
use Modules\Core\Services\ApprovalService;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\BayarDariSaldoService;
use Modules\Keuangan\Services\PembayaranService;
use Modules\Keuangan\Services\TagihanService;
use Modules\Kesantrian\Models\Santri;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Models\SumberDana;
use Modules\Ledger\Services\JurnalService;
use Modules\Pajak\Database\Seeders\PajakSeeder;
use Modules\Payroll\Models\SlipGaji;
use Modules\Payroll\Services\PayrollService;
use Modules\Pengadaan\Models\Belanja;
use Modules\Pengadaan\Services\BelanjaService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G1-01 — Rollout Approval Workflow (F1) ke model uang/payroll. Membuktikan:
 * (a) TANPA ApprovalRule → gate no-op murni, alur normal lolos (nol regresi);
 * (b) DENGAN rule wajib → operasi terblokir SEBELUM posting s/d disetujui;
 * (c) di bawah ambang → tak ter-gate; (d) Jurnal non-blocking + accessor;
 * (e) isolasi tenant.
 */
class ApprovalRolloutTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'approval_rule', 'approval_request',
            'akun', 'jurnal', 'jurnal_baris', 'sumber_dana', 'periode_akuntansi',
            'santri', 'kelas', 'tagihan', 'pembayaran', 'belanja', 'slip_gaji',
            'tabungan', 'tabungan_transaksi',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix, bool $pajak = false): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
        if ($pajak) {
            (new PajakSeeder)->run();
        }
        $this->userId = (int) User::withoutGlobalScopes()->create([
            'name' => 'Admin', 'email' => 'a'.str()->random(5).'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ])->id;
    }

    private function rule(string $type, string $field, float $nilai, string $op = '>='): ApprovalRule
    {
        return ApprovalRule::create([
            'approvable_type' => $type, 'nama' => 'Aturan '.class_basename($type),
            'field' => $field, 'operator' => $op, 'nilai' => $nilai,
            'approver_role' => 'bendahara', 'mode' => 'wajib', 'urutan' => 1, 'aktif' => true,
        ]);
    }

    /** Ajukan + setujui persetujuan untuk satu model (idempoten). */
    private function approve(Model $m): void
    {
        $req = $m->ajukanPersetujuan($this->userId);
        $this->assertNotNull($req, 'ajukanPersetujuan harus membuat request bila rule terpicu');
        app(ApprovalService::class)->setujui($req, $this->userId);
    }

    private function santri(): Santri
    {
        return Santri::create([
            'nis' => 'AR-'.str()->random(5), 'nama' => 'Santri', 'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
    }

    // ---------- BELANJA ----------

    public function test_belanja_tanpa_rule_posting_normal(): void
    {
        $this->boot('ar-bel1', pajak: true);
        $svc = app(BelanjaService::class);
        $belanja = $svc->buat(['uraian' => 'Beli laptop', 'jenis' => 'barang',
            'sumber_dana_id' => SumberDana::where('kode_dana', '03')->value('id'), 'dpp' => 3000000, 'vendor_npwp' => true]);

        $belanja = $svc->setujui($belanja, approvedBy: null);
        $this->assertSame('dicairkan', $belanja->status_approval);
        $this->assertNotNull($belanja->jurnal_id);
        tenancy()->end();
    }

    public function test_belanja_dengan_rule_wajib_terblokir_sebelum_posting(): void
    {
        $this->boot('ar-bel2', pajak: true);
        $this->rule(Belanja::class, 'total', 1000000);
        $svc = app(BelanjaService::class);
        $belanja = $svc->buat(['uraian' => 'Beli server', 'jenis' => 'barang',
            'sumber_dana_id' => SumberDana::where('kode_dana', '03')->value('id'), 'dpp' => 3000000, 'vendor_npwp' => true]);

        try {
            $svc->setujui($belanja, approvedBy: null);
            $this->fail('Seharusnya terblokir persetujuan.');
        } catch (PersetujuanBelumLengkapException) {
            // Gate sebelum posting → tidak ada jurnal, status belum berubah.
            $this->assertNull($belanja->fresh()->jurnal_id);
            $this->assertSame('diajukan', $belanja->fresh()->status_approval);
        }

        $this->approve($belanja->fresh());
        $belanja = $svc->setujui($belanja->fresh(), approvedBy: null);
        $this->assertSame('dicairkan', $belanja->status_approval);
        $this->assertNotNull($belanja->jurnal_id);
        tenancy()->end();
    }

    // ---------- SLIP GAJI ----------

    private function slip(float $pokok = 3000000): SlipGaji
    {
        $ptk = User::withoutGlobalScopes()->create([
            'name' => 'Ustadz', 'email' => 'u'.str()->random(5).'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);

        return app(PayrollService::class)->buatSlip([
            'users_id' => $ptk->id, 'periode' => '2026-06', 'honor_pokok' => $pokok,
        ]);
    }

    public function test_slip_tanpa_rule_finalisasi_normal(): void
    {
        $this->boot('ar-slp1');
        $slip = app(PayrollService::class)->finalisasi($this->slip());
        $this->assertSame('final', $slip->status);
        $this->assertNotNull($slip->jurnal_id);
        tenancy()->end();
    }

    public function test_slip_dengan_rule_wajib_terblokir(): void
    {
        $this->boot('ar-slp2');
        $this->rule(SlipGaji::class, 'bruto', 1000000);
        $slip = $this->slip(3000000);

        try {
            app(PayrollService::class)->finalisasi($slip);
            $this->fail('Seharusnya terblokir.');
        } catch (PersetujuanBelumLengkapException) {
            $this->assertSame('draft', $slip->fresh()->status);
            $this->assertNull($slip->fresh()->jurnal_id);
        }

        $this->approve($slip->fresh());
        $slip = app(PayrollService::class)->finalisasi($slip->fresh());
        $this->assertSame('final', $slip->status);
        tenancy()->end();
    }

    // ---------- PEMBAYARAN (jalur uang LIVE) ----------

    private function tagihanBayar(float $tagihanNominal, float $bayar): Pembayaran
    {
        $tagihan = (new TagihanService)->buatTagihanUntukSantri($this->santri(), [
            'jenis_tagihan' => 'spp', 'nominal' => $tagihanNominal, 'jatuh_tempo' => '2026-07-10', 'bulan_masehi' => '2026-07',
        ]);

        return (new PembayaranService)->inputPembayaran($tagihan->fresh(), ['nominal' => $bayar]);
    }

    public function test_pembayaran_tanpa_rule_verifikasi_normal(): void
    {
        $this->boot('ar-bay1');
        $bayar = $this->tagihanBayar(500000, 500000);
        (new PembayaranService)->verifikasiPembayaran($bayar, $this->userId);
        $this->assertTrue($bayar->fresh()->is_verified);
        tenancy()->end();
    }

    public function test_pembayaran_besar_dengan_rule_terblokir(): void
    {
        $this->boot('ar-bay2');
        $this->rule(Pembayaran::class, 'nominal', 5000000);
        $bayar = $this->tagihanBayar(6000000, 6000000);

        try {
            (new PembayaranService)->verifikasiPembayaran($bayar, $this->userId);
            $this->fail('Pembayaran besar harus terblokir.');
        } catch (PersetujuanBelumLengkapException) {
            $this->assertFalse($bayar->fresh()->is_verified);
        }

        $this->approve($bayar->fresh());
        (new PembayaranService)->verifikasiPembayaran($bayar->fresh(), $this->userId);
        $this->assertTrue($bayar->fresh()->is_verified);
        tenancy()->end();
    }

    public function test_pembayaran_di_bawah_ambang_tidak_tergate(): void
    {
        $this->boot('ar-bay3');
        $this->rule(Pembayaran::class, 'nominal', 5000000);
        $bayar = $this->tagihanBayar(1000000, 1000000);
        (new PembayaranService)->verifikasiPembayaran($bayar, $this->userId);
        $this->assertTrue($bayar->fresh()->is_verified);
        tenancy()->end();
    }

    // ---------- PEMBAYARAN via SALDO TABUNGAN (gate konsisten lintas-metode) ----------

    public function test_bayar_saldo_tabungan_tanpa_rule_normal(): void
    {
        $this->boot('ar-sld1');
        $santri = $this->santri();
        Tabungan::create(['santri_id' => $santri->id, 'saldo' => 1000000, 'is_frozen' => false]);
        $tagihan = (new TagihanService)->buatTagihanUntukSantri($santri, [
            'jenis_tagihan' => 'spp', 'nominal' => 300000, 'jatuh_tempo' => '2026-07-10', 'bulan_masehi' => '2026-07',
        ]);

        $bayar = app(BayarDariSaldoService::class)->bayar($santri, $tagihan->fresh(), 300000, $this->userId);
        $this->assertTrue($bayar->fresh()->is_verified);
        $this->assertEqualsWithDelta(700000, (float) Tabungan::where('santri_id', $santri->id)->value('saldo'), 0.01);
        tenancy()->end();
    }

    public function test_bayar_saldo_tabungan_dengan_rule_terblokir_dan_rollback(): void
    {
        $this->boot('ar-sld2');
        $this->rule(Pembayaran::class, 'nominal', 5000000);
        $santri = $this->santri();
        Tabungan::create(['santri_id' => $santri->id, 'saldo' => 10000000, 'is_frozen' => false]);
        $tagihan = (new TagihanService)->buatTagihanUntukSantri($santri, [
            'jenis_tagihan' => 'spp', 'nominal' => 6000000, 'jatuh_tempo' => '2026-07-10', 'bulan_masehi' => '2026-07',
        ]);

        try {
            app(BayarDariSaldoService::class)->bayar($santri, $tagihan->fresh(), 6000000, $this->userId);
            $this->fail('Bayar saldo besar harus terblokir persetujuan.');
        } catch (PersetujuanBelumLengkapException) {
            // Seluruh transaksi rollback: saldo utuh, tak ada pembayaran/penarikan.
            $this->assertEqualsWithDelta(10000000, (float) Tabungan::where('santri_id', $santri->id)->value('saldo'), 0.01);
            $this->assertSame(0, Pembayaran::query()->count());
        }
        tenancy()->end();
    }

    // ---------- JURNAL MANUAL (non-blocking) ----------

    private function postJurnal(float $nominal): Jurnal
    {
        return app(JurnalService::class)->postJurnal([
            'tanggal' => now()->toDateString(), 'deskripsi' => 'Jurnal uji', 'tipe_sumber' => 'manual',
            'baris' => [
                ['akun_id' => (int) Akun::where('kode', '1-1000')->value('id'), 'debit' => $nominal, 'kredit' => 0],
                ['akun_id' => (int) Akun::where('kode', '4-1000')->value('id'), 'debit' => 0, 'kredit' => $nominal],
            ],
        ]);
    }

    public function test_jurnal_total_debit_accessor(): void
    {
        $this->boot('ar-jrn1');
        $jurnal = $this->postJurnal(7500000);
        $this->assertEqualsWithDelta(7500000, $jurnal->total_debit, 0.01);
        tenancy()->end();
    }

    public function test_jurnal_ajukan_non_blocking(): void
    {
        $this->boot('ar-jrn2');
        // Tanpa rule → ajukanPersetujuan null, jurnal tetap terposting.
        $j1 = $this->postJurnal(2000000);
        $this->assertNull($j1->ajukanPersetujuan($this->userId));
        $this->assertSame('posted', $j1->fresh()->status);

        // Dengan rule total_debit>=10jt → request menunggu dibuat, jurnal TETAP terposting.
        $this->rule(Jurnal::class, 'total_debit', 10000000);
        $j2 = $this->postJurnal(12000000);
        $req = $j2->ajukanPersetujuan($this->userId);
        $this->assertNotNull($req);
        $this->assertSame('menunggu', $req->status);
        $this->assertSame('posted', $j2->fresh()->status); // non-blocking
        tenancy()->end();
    }

    // ---------- ISOLASI ----------

    public function test_isolasi_tenant(): void
    {
        $this->boot('ar-isoA');
        $this->rule(Pembayaran::class, 'nominal', 1);
        $bayar = $this->tagihanBayar(100000, 100000);
        $bayar->ajukanPersetujuan($this->userId);
        $this->assertSame(1, ApprovalRule::query()->count());
        tenancy()->end();

        $this->boot('ar-isoB');
        $this->assertSame(0, ApprovalRule::query()->count());
        // Tanpa rule di B → verifikasi lolos normal.
        $bayar = $this->tagihanBayar(100000, 100000);
        (new PembayaranService)->verifikasiPembayaran($bayar, $this->userId);
        $this->assertTrue($bayar->fresh()->is_verified);
        tenancy()->end();
    }
}
