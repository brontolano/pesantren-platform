<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\BayarDariSaldoService;
use Modules\Keuangan\Services\TabunganService;
use Modules\Kesantrian\Models\Santri;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Services\BukuBesarService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class TabunganJurnalTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurePpdbTestingDatabase();
        if (! Schema::connection('pgsql')->hasTable('laporan_jasa_dan_barang')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(): Santri
    {
        $tenant = $this->createRuntimeTenant('tab', 'Pesantren Tabungan');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();

        return Santri::create(['nis' => 'S-' . str()->random(4), 'nama' => 'Santri', 'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L']);
    }

    public function test_setor_tabungan_auto_jurnal_seimbang(): void
    {
        $santri = $this->boot();
        app(TabunganService::class)->setor($santri, 100000);

        $jurnal = Jurnal::where('ref_type', 'TabunganTransaksi')->where('tipe_sumber', 'tabungan')->first();
        $this->assertNotNull($jurnal, 'Setor tabungan harus auto-jurnal.');

        $kasId = Akun::where('kode', '1-1000')->value('id');
        $titipanId = Akun::where('kode', '2-3000')->value('id');
        $this->assertTrue($jurnal->baris()->where('akun_id', $kasId)->where('debit', 100000)->exists());
        $this->assertTrue($jurnal->baris()->where('akun_id', $titipanId)->where('kredit', 100000)->exists());
    }

    public function test_tarik_tabungan_kebalikan(): void
    {
        $santri = $this->boot();
        $svc = app(TabunganService::class);
        $svc->setor($santri, 100000);
        $svc->tarik($santri, 30000);

        // Saldo kas = 100k - 30k = 70k
        $this->assertEqualsWithDelta(70000, app(BukuBesarService::class)->bku()['saldo_akhir'], 0.01);
        $this->assertSame(2, Jurnal::where('ref_type', 'TabunganTransaksi')->count());
    }

    /**
     * REGRESI (audit #1): bayar tagihan dari saldo tabungan WAJIB terbukukan di
     * buku besar. Sebelum perbaikan, BayarDariSaldoService menulis transaksi
     * inline tanpa event → NOL jurnal (pendapatan SPP understated, titipan
     * overstated). Net dua jurнal: D Titipan Tabungan (2-3000) / K Pendapatan SPP (4-1000).
     */
    public function test_bayar_dari_saldo_membukukan_titipan_jadi_pendapatan(): void
    {
        $santri = $this->boot();
        $svc = app(TabunganService::class);
        $svc->setor($santri, 200000); // D Kas / K Titipan 200k

        $tagihan = Tagihan::create([
            'santri_id' => $santri->id, 'jenis_tagihan' => 'SPP',
            'nominal' => 150000, 'nominal_akhir' => 150000, 'status' => 'belum_dibayar',
            'jatuh_tempo' => now()->addDays(7)->toDateString(),
        ]);

        app(BayarDariSaldoService::class)->bayar($santri, $tagihan, 150000);

        // Jalur pembayaran-dari-saldo HARUS memancarkan kedua event → dua jurnal:
        //   tarik      (ref TabunganTransaksi) : D Titipan / K Kas
        //   pembayaran (ref Pembayaran)        : D Kas / K Pendapatan SPP
        $this->assertSame(2, Jurnal::where('ref_type', 'TabunganTransaksi')->count(), 'Setor + tarik = 2 jurnal tabungan.');
        $this->assertNotNull(
            Jurnal::where('ref_type', 'Pembayaran')->first(),
            'Bayar dari saldo HARUS auto-jurnal pendapatan (regresi audit #1).'
        );

        // Net antar-jurnal: Titipan ter-debit 150k (berkurang), Pendapatan SPP ter-kredit 150k.
        $titipanId = Akun::where('kode', '2-3000')->value('id');
        $pendapatanId = Akun::where('kode', '4-1000')->value('id');
        $baris = Jurnal::with('baris')->get()->flatMap->baris;

        $this->assertEqualsWithDelta(150000, (float) $baris->where('akun_id', $titipanId)->sum('debit'), 0.01, 'Titipan tabungan harus berkurang (debit) 150k.');
        $this->assertEqualsWithDelta(150000, (float) $baris->where('akun_id', $pendapatanId)->sum('kredit'), 0.01, 'Pendapatan SPP harus diakui (kredit) 150k.');

        // Konsistensi operasional tetap: tagihan lunas, saldo sisa 50k.
        $this->assertSame('lunas', $tagihan->fresh()->status);
        $this->assertEqualsWithDelta(50000, $svc->getSaldo($santri), 0.01);
    }
}
