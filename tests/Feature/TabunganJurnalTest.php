<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Services\TabunganService;
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

        return Santri::create(['nis' => 'S-'.str()->random(4), 'nama' => 'Santri', 'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L']);
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
}
