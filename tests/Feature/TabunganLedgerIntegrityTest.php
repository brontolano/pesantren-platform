<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\TabunganTopup;
use Modules\Keuangan\Models\TabunganTransaksi;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\BayarDariSaldoService;
use Modules\Keuangan\Services\PembayaranService;
use Modules\Keuangan\Services\TabunganService;
use Modules\Keuangan\Services\TopupService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * W3.11/W3.12 — Integritas ledger saldo tabungan (jalur uang kritis PWA Wali).
 *
 * Konkurensi nyata tak bisa disimulasikan di satu proses phpunit; yang dikunci
 * di sini adalah SEMANTIK GERBANG yang membuat operasi race-safe bersama
 * lockForUpdate di service: (a) setujui/tolak topup menolak status non-menunggu
 * pada baris ter-lock dalam transaksi, (b) validasi sisa tagihan dilakukan di
 * dalam transaksi, (c) rantai saldo_sebelum/sesudah konsisten.
 */
class TabunganLedgerIntegrityTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'tabungan',
            'tabungan_transaksi', 'tabungan_topup', 'tagihan', 'pembayaran',
        ]);

        $this->truncatePpdbTables([
            'tabungan_topup', 'tabungan_transaksi', 'tabungan',
            'pembayaran', 'tagihan', 'santri', 'domains', 'tenants',
        ]);
    }

    private int $userId;

    /** @return array{0:Santri,1:Tabungan} */
    private function boot(string $prefix, float $saldo = 0): array
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        // created_by/verified_by ber-FK ke users — butuh user nyata.
        $this->userId = (int) User::withoutGlobalScopes()->create([
            'name' => 'Bendahara Uji', 'email' => $prefix.'@uji.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ])->id;

        $santri = Santri::create([
            'nis' => strtoupper($prefix).'-001', 'nama' => 'Santri Uji',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
        $tabungan = Tabungan::create(['santri_id' => $santri->id, 'saldo' => $saldo]);

        return [$santri, $tabungan];
    }

    private function topup(Tabungan $tabungan, float $nominal = 100000): TabunganTopup
    {
        return app(TopupService::class)->ajukan($tabungan, [
            'nominal' => $nominal, 'tanggal_transfer' => '2026-06-10',
            'nama_pengirim' => 'Wali Uji', 'bukti' => 'topup/bukti.jpg',
        ]);
    }

    private function tagihan(Santri $santri, float $nominal): Tagihan
    {
        return Tagihan::create([
            'santri_id' => $santri->id, 'jenis_tagihan' => 'SPP',
            'nominal' => $nominal, 'nominal_akhir' => $nominal,
            'status' => 'belum_dibayar',
            'jatuh_tempo' => now()->addDays(7)->toDateString(),
        ]);
    }

    public function test_setujui_topup_dua_kali_tidak_menggandakan_saldo(): void
    {
        [, $tabungan] = $this->boot('tli1');
        $topup = $this->topup($tabungan, 100000);
        $svc = app(TopupService::class);

        $svc->setujui($topup, $this->userId);
        $this->assertEqualsWithDelta(100000, (float) $tabungan->fresh()->saldo, 0.01);

        // Persetujuan kedua (double-click/dua admin) WAJIB ditolak — saldo TIDAK dobel.
        try {
            $svc->setujui($topup->fresh(), $this->userId);
            $this->fail('Persetujuan kedua harus ditolak.');
        } catch (InvalidArgumentException) {
        }
        $this->assertEqualsWithDelta(100000, (float) $tabungan->fresh()->saldo, 0.01, 'Saldo tak boleh naik dobel.');
        $this->assertSame(1, TabunganTransaksi::where('tabungan_id', $tabungan->id)->count(), 'Setoran hanya boleh tercatat sekali.');
        tenancy()->end();
    }

    public function test_tolak_setelah_disetujui_ditolak_saldo_utuh(): void
    {
        [, $tabungan] = $this->boot('tli2');
        $topup = $this->topup($tabungan, 50000);
        $svc = app(TopupService::class);
        $svc->setujui($topup, $this->userId);

        $this->expectException(InvalidArgumentException::class);
        try {
            $svc->tolak($topup->fresh(), $this->userId, 'salah');
        } finally {
            $this->assertEqualsWithDelta(50000, (float) $tabungan->fresh()->saldo, 0.01);
            $this->assertSame('disetujui', $topup->fresh()->status, 'Status tak boleh tertimpa ditolak.');
            tenancy()->end();
        }
    }

    public function test_bayar_dari_saldo_atomik_dan_rantai_saldo_konsisten(): void
    {
        [$santri, $tabungan] = $this->boot('tli3', 200000);
        $tagihan = $this->tagihan($santri, 150000);

        $pembayaran = app(BayarDariSaldoService::class)->bayar($santri, $tagihan, 150000, $this->userId);

        $this->assertTrue((bool) $pembayaran->is_verified);
        $this->assertEqualsWithDelta(50000, (float) $tabungan->fresh()->saldo, 0.01);
        $this->assertSame('lunas', $tagihan->fresh()->status);

        $trx = TabunganTransaksi::where('tabungan_id', $tabungan->id)->orderBy('id')->get();
        $this->assertCount(1, $trx);
        $this->assertSame('tarik', $trx[0]->tipe);
        $this->assertEqualsWithDelta(200000, (float) $trx[0]->saldo_sebelum, 0.01);
        $this->assertEqualsWithDelta(50000, (float) $trx[0]->saldo_sesudah, 0.01);
        tenancy()->end();
    }

    public function test_bayar_tagihan_lunas_ditolak_tanpa_memotong_saldo(): void
    {
        [$santri, $tabungan] = $this->boot('tli4', 500000);
        $tagihan = $this->tagihan($santri, 100000);
        $svc = app(BayarDariSaldoService::class);
        $svc->bayar($santri, $tagihan, 100000, $this->userId); // lunas

        // Pembayaran kedua pada tagihan lunas WAJIB ditolak (cek status DI DALAM
        // transaksi + lock tagihan) → tak ada overpayment & saldo tak terpotong lagi.
        try {
            $svc->bayar($santri, $tagihan->fresh(), 100000, $this->userId);
            $this->fail('Bayar tagihan lunas harus ditolak.');
        } catch (InvalidArgumentException) {
        }
        $this->assertEqualsWithDelta(400000, (float) $tabungan->fresh()->saldo, 0.01);
        $this->assertSame(1, TabunganTransaksi::where('tabungan_id', $tabungan->id)->count());
        tenancy()->end();
    }

    public function test_bayar_melebihi_sisa_dan_saldo_kurang_ditolak(): void
    {
        [$santri, $tabungan] = $this->boot('tli5', 30000);
        $tagihan = $this->tagihan($santri, 100000);
        $svc = app(BayarDariSaldoService::class);

        try {
            $svc->bayar($santri, $tagihan, 150000, $this->userId); // > sisa tagihan
            $this->fail('Nominal melebihi sisa harus ditolak.');
        } catch (InvalidArgumentException) {
        }
        try {
            $svc->bayar($santri, $tagihan, 50000, $this->userId); // > saldo (30rb)
            $this->fail('Saldo kurang harus ditolak.');
        } catch (InvalidArgumentException) {
        }
        $this->assertEqualsWithDelta(30000, (float) $tabungan->fresh()->saldo, 0.01, 'Saldo utuh saat semua validasi menolak.');
        $this->assertSame(0, TabunganTransaksi::where('tabungan_id', $tabungan->id)->count());
        tenancy()->end();
    }

    public function test_tarik_melebihi_saldo_ditolak_di_dalam_lock(): void
    {
        [$santri, $tabungan] = $this->boot('tli7', 100000);
        $svc = app(TabunganService::class);

        $svc->tarik($santri, 80000, ['created_by' => $this->userId]);
        $this->assertEqualsWithDelta(20000, (float) $tabungan->fresh()->saldo, 0.01);

        // Penarikan kedua melebihi sisa — WAJIB ditolak oleh validasi ulang pada
        // baris ter-lock (guard overdraft), saldo tak boleh negatif.
        try {
            $svc->tarik($santri, 80000, ['created_by' => $this->userId]);
            $this->fail('Tarik melebihi saldo harus ditolak.');
        } catch (InvalidArgumentException) {
        }
        $this->assertEqualsWithDelta(20000, (float) $tabungan->fresh()->saldo, 0.01, 'Saldo tak boleh negatif.');
        $this->assertSame(1, TabunganTransaksi::where('tabungan_id', $tabungan->id)->where('tipe', 'tarik')->count());
        tenancy()->end();
    }

    public function test_verifikasi_pembayaran_dua_kali_ditolak(): void
    {
        [$santri] = $this->boot('tli8');
        $tagihan = $this->tagihan($santri, 100000);
        $bayar = Pembayaran::create([
            'tagihan_id' => $tagihan->id, 'santri_id' => $santri->id, 'nominal' => 100000,
            'metode' => 'transfer', 'tanggal_pembayaran' => now()->toDateString(), 'is_verified' => false,
        ]);
        $svc = app(PembayaranService::class);

        $svc->verifikasiPembayaran($bayar, $this->userId);
        $this->assertSame('lunas', $tagihan->fresh()->status);

        try {
            $svc->verifikasiPembayaran($bayar->fresh(), $this->userId); // double-verify
            $this->fail('Verifikasi kedua harus ditolak.');
        } catch (InvalidArgumentException) {
        }
        tenancy()->end();
    }

    public function test_verifikasi_overshoot_lintas_bukti_ditolak(): void
    {
        [$santri] = $this->boot('tli9');
        $tagihan = $this->tagihan($santri, 100000);
        // Wali tak sengaja unggah DUA bukti bernilai penuh → dua pembayaran pending.
        $b1 = Pembayaran::create([
            'tagihan_id' => $tagihan->id, 'santri_id' => $santri->id, 'nominal' => 100000,
            'metode' => 'transfer', 'tanggal_pembayaran' => now()->toDateString(), 'is_verified' => false,
        ]);
        $b2 = Pembayaran::create([
            'tagihan_id' => $tagihan->id, 'santri_id' => $santri->id, 'nominal' => 100000,
            'metode' => 'transfer', 'tanggal_pembayaran' => now()->toDateString(), 'is_verified' => false,
        ]);
        $svc = app(PembayaranService::class);

        $svc->verifikasiPembayaran($b1, $this->userId); // lunas
        // Verifikasi bukti kedua WAJIB ditolak: total verified akan MELEBIHI tagihan.
        try {
            $svc->verifikasiPembayaran($b2, $this->userId);
            $this->fail('Verifikasi overshoot harus ditolak.');
        } catch (InvalidArgumentException) {
        }
        $totalVerified = (float) Pembayaran::where('tagihan_id', $tagihan->id)
            ->where('is_verified', true)->sum('nominal');
        $this->assertEqualsWithDelta(100000, $totalVerified, 0.01, 'Total verified tak boleh melebihi tagihan.');
        tenancy()->end();
    }

    public function test_frozen_dan_limit_harian_menolak_tarik(): void
    {
        [$santri, $tabungan] = $this->boot('tli10', 500000);
        $svc = app(TabunganService::class);

        // Limit harian 100rb: tarik 80rb OK, tarik 30rb lagi DITOLAK (total > limit).
        $tabungan->update(['limit_harian' => 100000]);
        $svc->tarik($santri, 80000, ['created_by' => $this->userId]);
        try {
            $svc->tarik($santri, 30000, ['created_by' => $this->userId]);
            $this->fail('Melebihi limit harian harus ditolak.');
        } catch (InvalidArgumentException) {
        }
        $this->assertEqualsWithDelta(420000, (float) $tabungan->fresh()->saldo, 0.01);

        // Dibekukan: tarik DITOLAK meski saldo & limit cukup; setor tetap boleh.
        $tabungan->update(['is_frozen' => true, 'limit_harian' => null]);
        try {
            $svc->tarik($santri, 10000, ['created_by' => $this->userId]);
            $this->fail('Tarik saat dibekukan harus ditolak.');
        } catch (InvalidArgumentException) {
        }
        $svc->setor($santri, 5000, ['created_by' => $this->userId]); // setor boleh
        $this->assertEqualsWithDelta(425000, (float) $tabungan->fresh()->saldo, 0.01);
        tenancy()->end();
    }

    public function test_tabungan_dibekukan_menolak_pembayaran(): void
    {
        [$santri, $tabungan] = $this->boot('tli6', 100000);
        $tabungan->update(['is_frozen' => true]);
        $tagihan = $this->tagihan($santri, 50000);

        $this->expectException(InvalidArgumentException::class);
        try {
            app(BayarDariSaldoService::class)->bayar($santri, $tagihan, 50000, $this->userId);
        } finally {
            $this->assertEqualsWithDelta(100000, (float) $tabungan->fresh()->saldo, 0.01);
            tenancy()->end();
        }
    }

    private function seedTenantRow(Tenant $tenant): void
    {
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            [
                'name' => $tenant->name, 'is_active' => $tenant->is_active,
                'data' => json_encode([]), 'created_at' => now(), 'updated_at' => now(),
            ],
        );
    }
}
