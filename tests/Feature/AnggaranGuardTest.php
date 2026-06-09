<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Exceptions\AnggaranTerlampauiException;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Anggaran;
use Modules\Ledger\Services\AnggaranGuardService;
use Modules\Pajak\Database\Seeders\PajakSeeder;
use Modules\Pengadaan\Models\Belanja;
use Modules\Pengadaan\Services\BelanjaService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Paket D (adaptasi Budget Control ERPNext): guard anggaran RKAM saat belanja
 * disetujui — peringatan (boleh) vs blokir (ditolak), realisasi auditable,
 * no-op tanpa pagu.
 */
class AnggaranGuardTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('anggaran') || ! Schema::connection('pgsql')->hasTable('belanja')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(): void
    {
        $tenant = $this->createRuntimeTenant('angg', 'Pesantren Anggaran');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
        (new PajakSeeder)->run();
    }

    private function akunBebanId(): int
    {
        return (int) Akun::query()->where('kode', '5-1000')->value('id');
    }

    private function belanja(float $dpp): Belanja
    {
        return app(BelanjaService::class)->buat([
            'dpp' => $dpp,
            'jenis' => 'barang',
            'uraian' => 'Belanja uji anggaran',
            'akun_beban_id' => $this->akunBebanId(),
            'tanggal' => now()->toDateString(),
        ]);
    }

    private function anggaran(float $nominal, string $mode): Anggaran
    {
        return Anggaran::create([
            'tahun_anggaran' => (string) now()->year,
            'akun_id' => $this->akunBebanId(),
            'divisi_id' => null,
            'sumber_dana_id' => null,
            'nominal_anggaran' => $nominal,
            'mode' => $mode,
        ]);
    }

    public function test_belanja_lampaui_pagu_mode_blokir_ditolak(): void
    {
        $this->boot();
        $this->anggaran(100000, 'blokir');
        $belanja = $this->belanja(500000); // > pagu 100rb

        $this->expectException(AnggaranTerlampauiException::class);
        app(BelanjaService::class)->setujui($belanja);
    }

    public function test_belanja_lampaui_pagu_mode_peringatan_tetap_disetujui(): void
    {
        $this->boot();
        $this->anggaran(100000, 'peringatan');
        $belanja = $this->belanja(500000);

        $disetujui = app(BelanjaService::class)->setujui($belanja);
        $this->assertSame('dicairkan', $disetujui->status_approval);
        $this->assertNotNull($disetujui->jurnal_id);
    }

    public function test_tanpa_pagu_anggaran_belanja_lolos(): void
    {
        $this->boot();
        $belanja = $this->belanja(500000); // tak ada baris anggaran

        $disetujui = app(BelanjaService::class)->setujui($belanja);
        $this->assertSame('dicairkan', $disetujui->status_approval);
    }

    public function test_cek_menghitung_realisasi_dan_sisa(): void
    {
        $this->boot();
        $svc = app(AnggaranGuardService::class);
        $akun = $this->akunBebanId();
        $tahun = (string) now()->year;

        // Setujui 1 belanja Rp200rb (peringatan agar lolos) → realisasi beban.
        $this->anggaran(1000000, 'peringatan');
        app(BelanjaService::class)->setujui($this->belanja(200000));

        $hasil = $svc->cek($akun, null, null, $tahun, 0);
        $this->assertTrue($hasil['ada_anggaran']);
        $this->assertEqualsWithDelta(200000, (float) $hasil['realisasi'], 0.01);
        $this->assertEqualsWithDelta(800000, (float) $hasil['sisa'], 0.01);
        $this->assertSame('ok', $hasil['status']);
    }

    public function test_isolasi_tenant_anggaran(): void
    {
        $this->boot();
        $this->anggaran(100000, 'blokir');
        $this->assertSame(1, Anggaran::query()->count());

        tenancy()->end();

        $this->boot();
        $this->assertSame(0, Anggaran::query()->count(), 'Anggaran tenant lain tak boleh terlihat.');
    }
}
