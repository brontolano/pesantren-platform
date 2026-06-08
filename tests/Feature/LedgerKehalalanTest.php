<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Exceptions\PelanggaranKehalalanException;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\SumberDana;
use Modules\Ledger\Services\DanaKebajikanService;
use Modules\Ledger\Services\JurnalService;
use Modules\Ledger\Support\FiturKeuangan;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class LedgerKehalalanTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('kebijakan_kehalalan')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function bootTenant(string $nama, bool $pemisahanAktif): void
    {
        $tenant = $this->createRuntimeTenant('halal', $nama);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();

        if ($pemisahanAktif) {
            Setting::create(['key' => FiturKeuangan::KEY_PEMISAHAN_KEHALALAN, 'value' => true]);
        }
    }

    /** Manual jurnal: dana syubhat (90) dikreditkan ke akun pendapatan (4-1000). */
    private function postSyubhatKePendapatan(): void
    {
        app(JurnalService::class)->postJurnal([
            'deskripsi' => 'uji',
            'baris' => [
                ['akun_id' => Akun::where('kode', '1-1000')->value('id'), 'sumber_dana_id' => SumberDana::where('kode_dana', '90')->value('id'), 'debit' => 100000],
                ['akun_id' => Akun::where('kode', '4-1000')->value('id'), 'sumber_dana_id' => SumberDana::where('kode_dana', '90')->value('id'), 'kredit' => 100000],
            ],
        ]);
    }

    public function test_toggle_off_menonaktifkan_seluruh_penjagaan(): void
    {
        $this->bootTenant('Tanpa Pemisahan', pemisahanAktif: false);

        $this->assertFalse(FiturKeuangan::pemisahanKehalalanAktif());

        // Tanpa pemisahan, posting yang "melanggar" pun lolos (keuangan biasa).
        $this->postSyubhatKePendapatan();
        $this->assertSame(1, \Modules\Ledger\Models\Jurnal::count());
    }

    public function test_terima_dana_syubhat_sebagai_dana_kebajikan_bukan_pendapatan(): void
    {
        $this->bootTenant('Dengan Pemisahan', pemisahanAktif: true);

        $svc = app(DanaKebajikanService::class);
        $jurnal = $svc->terima(500000, '90');

        // Kredit jatuh ke Dana Kebajikan (2-2000), bukan akun pendapatan.
        $danaKebajikanId = Akun::where('kode', '2-2000')->value('id');
        $this->assertTrue($jurnal->baris()->where('akun_id', $danaKebajikanId)->where('kredit', 500000)->exists());
        $this->assertEqualsWithDelta(500000, $svc->saldo(), 0.01);
    }

    public function test_salurkan_ke_fasilitas_umum_diizinkan_dan_mengurangi_saldo(): void
    {
        $this->bootTenant('Penyaluran Sah', pemisahanAktif: true);

        $svc = app(DanaKebajikanService::class);
        $svc->terima(500000, '90');
        $svc->salurkan(200000, '2-2010', '90'); // fasilitas umum (MCK/jalan)

        $this->assertEqualsWithDelta(300000, $svc->saldo(), 0.01);
    }

    public function test_dana_syubhat_dilarang_untuk_sarana_ibadah(): void
    {
        $this->bootTenant('Larangan Masjid', pemisahanAktif: true);

        $svc = app(DanaKebajikanService::class);
        $svc->terima(500000, '90');

        $this->expectException(PelanggaranKehalalanException::class);
        $svc->salurkan(100000, '5-6000', '90'); // 5-6000 = sarana ibadah → DILARANG
    }

    public function test_dana_syubhat_tidak_boleh_jadi_pendapatan(): void
    {
        $this->bootTenant('No Income', pemisahanAktif: true);

        $this->expectException(PelanggaranKehalalanException::class);
        $this->postSyubhatKePendapatan();
    }

    public function test_tidak_boleh_mencampur_dana_halal_dan_syubhat(): void
    {
        $this->bootTenant('No Mix', pemisahanAktif: true);

        $this->expectException(PelanggaranKehalalanException::class);
        app(JurnalService::class)->postJurnal([
            'deskripsi' => 'campur',
            'baris' => [
                ['akun_id' => Akun::where('kode', '1-1000')->value('id'), 'sumber_dana_id' => SumberDana::where('kode_dana', '03')->value('id'), 'debit' => 100000], // halal
                ['akun_id' => Akun::where('kode', '2-2000')->value('id'), 'sumber_dana_id' => SumberDana::where('kode_dana', '90')->value('id'), 'kredit' => 100000], // syubhat
            ],
        ]);
    }
}
