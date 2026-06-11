<?php

namespace Tests\Feature;

use App\Models\AccessWali;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\SantriAccountService;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\TabunganTransaksi;
use Modules\Keuangan\Services\KantinService;
use Modules\Keuangan\Services\TabunganService;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Jurnal;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * POS Kantin via Tabungan (2b) — WP uang:
 * saldo tak minus, race lock, PIN wajib, batas harian (wali policy-first),
 * refund sekali saja, jurnal seimbang + idempoten, riwayat wali.
 */
class KantinPosTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->configurePpdbTestingDatabase();
        if (! Schema::connection('pgsql')->hasTable('tabungan_transaksi')
            || ! Schema::connection('pgsql')->hasColumn('tabungan', 'batas_belanja_harian')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $this->truncatePpdbTables([
            'access_wali', 'tabungan_transaksi', 'tabungan', 'santri', 'settings',
            'model_has_roles', 'roles', 'permissions', 'role_has_permissions',
            'users', 'domains', 'tenants',
        ]);
    }

    /** @return array{0:Santri,1:string} santri + PIN */
    private function boot(string $prefix, float $saldoAwal = 100000): array
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren Kantin');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        (new LedgerSeeder)->run();

        $santri = Santri::create([
            'nis' => strtoupper($prefix).'-001', 'nama' => 'Santri Kantin',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);

        app(SantriAccountService::class)->setPin($santri, '246810');

        if ($saldoAwal > 0) {
            app(TabunganService::class)->setor($santri, $saldoAwal);
        }

        return [$santri->fresh(), '246810'];
    }

    public function test_belanja_memotong_saldo_dan_tercatat(): void
    {
        [$santri, $pin] = $this->boot('kt1');

        $trx = app(KantinService::class)->belanja($santri, 15000, $pin, ['keterangan' => 'nasi + es teh']);

        $this->assertSame('belanja_kantin', $trx->tipe);
        $this->assertEqualsWithDelta(85000, app(TabunganService::class)->getSaldo($santri), 0.001);
    }

    public function test_saldo_tidak_bisa_minus(): void
    {
        [$santri, $pin] = $this->boot('kt2', 10000);

        try {
            app(KantinService::class)->belanja($santri, 10001, $pin);
            $this->fail('Belanja melebihi saldo harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Saldo tidak cukup', $e->getMessage());
        }

        $this->assertEqualsWithDelta(10000, app(TabunganService::class)->getSaldo($santri), 0.001);
        $this->assertSame(0, TabunganTransaksi::where('tipe', 'belanja_kantin')->count());
    }

    /**
     * Race 2 kasir: dua belanja yang MASING-MASING lolos cek saldo awal
     * (unlocked) tapi totalnya melebihi saldo — yang kedua HARUS ditolak oleh
     * re-check pada baris ter-lockForUpdate. Sekuensial membuktikan jalur
     * conditional/lock yang sama yang menyerialkan dua kasir simultan.
     */
    public function test_race_dua_kasir_saldo_akhir_benar(): void
    {
        [$santri, $pin] = $this->boot('kt3', 10000);
        $svc = app(KantinService::class);

        $svc->belanja($santri, 7000, $pin); // kasir A
        try {
            $svc->belanja($santri, 7000, $pin); // kasir B — saldo tinggal 3000
            $this->fail('Belanja kedua harus ditolak (saldo tidak cukup).');
        } catch (InvalidArgumentException) {
        }

        $this->assertEqualsWithDelta(3000, app(TabunganService::class)->getSaldo($santri), 0.001);
        $this->assertSame(1, TabunganTransaksi::where('tipe', 'belanja_kantin')->count());
    }

    public function test_pin_salah_dan_tanpa_pin_ditolak(): void
    {
        [$santri] = $this->boot('kt4');
        $svc = app(KantinService::class);

        foreach (['999999', ''] as $pinSalah) {
            try {
                $svc->belanja($santri, 5000, $pinSalah);
                $this->fail('Belanja dengan PIN "'.$pinSalah.'" harus ditolak.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('PIN', $e->getMessage());
            }
        }

        // Santri TANPA akun/PIN sama sekali juga ditolak.
        $tanpaAkun = Santri::create([
            'nis' => 'KT4-002', 'nama' => 'Tanpa Akun',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'P', 'status' => 'aktif',
        ]);
        app(TabunganService::class)->setor($tanpaAkun, 50000);
        try {
            $svc->belanja($tanpaAkun, 5000, '123456');
            $this->fail('Santri tanpa PIN harus ditolak.');
        } catch (InvalidArgumentException) {
        }

        $this->assertSame(0, TabunganTransaksi::where('tipe', 'belanja_kantin')->count());
        $this->assertEqualsWithDelta(100000, app(TabunganService::class)->getSaldo($santri), 0.001);
    }

    public function test_batas_harian_default_tenant_dan_per_santri(): void
    {
        [$santri, $pin] = $this->boot('kt5');
        $svc = app(KantinService::class);

        // Default tenant: 20.000/hari.
        Setting::create(['tenant_id' => tenant('id'), 'key' => 'kantin.batas_belanja_default', 'value' => 20000]);

        $svc->belanja($santri, 15000, $pin);
        try {
            $svc->belanja($santri, 6000, $pin); // 15k + 6k > 20k
            $this->fail('Melebihi batas default tenant harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('batas belanja harian', $e->getMessage());
        }

        // Per-santri override lebih longgar → boleh.
        Tabungan::where('santri_id', $santri->id)->update(['batas_belanja_harian' => 50000]);
        $svc->belanja($santri, 6000, $pin);
        $this->assertEqualsWithDelta(79000, app(TabunganService::class)->getSaldo($santri), 0.001);

        // 0 = blokir total.
        Tabungan::where('santri_id', $santri->id)->update(['batas_belanja_harian' => 0]);
        try {
            $svc->belanja($santri, 1000, $pin);
            $this->fail('Batas 0 harus memblokir belanja.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('diblokir', $e->getMessage());
        }
    }

    public function test_wali_set_batas_dari_portal_mengubah_enforcement(): void
    {
        $tenant = $this->createRuntimeTenant('kt6', 'Pesantren Kantin Wali');
        $host = $tenant->domains->first()->domain;
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        (new LedgerSeeder)->run();

        $wali = User::create(['tenant_id' => $tenant->id, 'name' => 'Wali', 'email' => 'kt6-wali@t.local', 'password' => 'password']);
        $wali->assignRole('wali');

        $santri = Santri::create(['nis' => 'KT6-001', 'nama' => 'Anak', 'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif']);
        AccessWali::create(['tenant_id' => $tenant->id, 'wali_id' => $wali->id, 'santri_id' => $santri->id, 'relationship_type' => 'ayah', 'is_primary' => true]);

        app(SantriAccountService::class)->setPin($santri, '246810');
        app(TabunganService::class)->setor($santri, 100000);

        // Sebelum diatur wali: tanpa batas — belanja 30k lolos.
        app(KantinService::class)->belanja($santri, 30000, '246810');

        // Wali set batas 35.000/hari dari Portal Wali (policy-first).
        $server = ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
        $this->actingAs($wali)
            ->post("http://{$host}/wali/santri/{$santri->id}/tabungan/batas-belanja", ['batas' => 35000], $server)
            ->assertRedirect();

        $this->assertEqualsWithDelta(35000, (float) Tabungan::where('santri_id', $santri->id)->value('batas_belanja_harian'), 0.001);

        // Enforcement berubah: total hari ini 30k, tambah 10k > 35k → ditolak.
        try {
            app(KantinService::class)->belanja($santri, 10000, '246810');
            $this->fail('Belanja melebihi batas yang di-set wali harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('batas belanja harian', $e->getMessage());
        }

        // Masih boleh belanja dalam sisa batas (5k).
        app(KantinService::class)->belanja($santri, 5000, '246810');
        $this->assertEqualsWithDelta(65000, app(TabunganService::class)->getSaldo($santri), 0.001);
    }

    public function test_refund_sekali_saja_dan_jurnal_seimbang_idempoten(): void
    {
        [$santri, $pin] = $this->boot('kt7');
        $svc = app(KantinService::class);

        $trx = $svc->belanja($santri, 12000, $pin);
        $refund = $svc->refund($trx, null, 'salah input');

        $this->assertSame('refund_kantin', $refund->tipe);
        $this->assertSame($trx->id, $refund->ref_transaksi_id);
        $this->assertEqualsWithDelta(100000, app(TabunganService::class)->getSaldo($santri), 0.001);

        // Refund kedua DITOLAK.
        try {
            $svc->refund($trx);
            $this->fail('Refund kedua harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('sudah pernah di-refund', $e->getMessage());
        }
        $this->assertSame(1, TabunganTransaksi::where('tipe', 'refund_kantin')->count());

        // Refund atas refund juga DITOLAK.
        try {
            $svc->refund($refund);
            $this->fail('Refund atas refund harus ditolak.');
        } catch (InvalidArgumentException) {
        }

        // Jurnal: belanja & refund masing-masing seimbang + idempotency_key terisi.
        foreach ([$trx->id, $refund->id] as $refId) {
            $jurnal = Jurnal::where('ref_type', 'TabunganTransaksi')->where('ref_id', (string) $refId)->first();
            $this->assertNotNull($jurnal, "Jurnal ref {$refId} harus ada.");
            $this->assertNotNull($jurnal->idempotency_key, 'idempotency_key TIDAK boleh null.');
            $this->assertEqualsWithDelta(
                (float) $jurnal->baris()->sum('debit'),
                (float) $jurnal->baris()->sum('kredit'),
                0.001,
                'Jurnal harus seimbang.'
            );
            $this->assertEqualsWithDelta(12000, (float) $jurnal->baris()->sum('debit'), 0.001);
        }
    }

    public function test_wali_melihat_riwayat_belanja_anak(): void
    {
        $tenant = $this->createRuntimeTenant('kt8', 'Pesantren Riwayat');
        $host = $tenant->domains->first()->domain;
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        (new LedgerSeeder)->run();

        $wali = User::create(['tenant_id' => $tenant->id, 'name' => 'Wali', 'email' => 'kt8-wali@t.local', 'password' => 'password']);
        $wali->assignRole('wali');
        $santri = Santri::create(['nis' => 'KT8-001', 'nama' => 'Anak', 'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif']);
        AccessWali::create(['tenant_id' => $tenant->id, 'wali_id' => $wali->id, 'santri_id' => $santri->id, 'relationship_type' => 'ayah', 'is_primary' => true]);

        app(SantriAccountService::class)->setPin($santri, '246810');
        app(TabunganService::class)->setor($santri, 50000);
        app(KantinService::class)->belanja($santri, 8000, '246810', ['keterangan' => 'bakso']);

        $server = ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
        $res = $this->actingAs($wali)->get("http://{$host}/wali/santri/{$santri->id}/tabungan", $server);
        $res->assertOk();

        $savings = $res->viewData('page')['props']['savings'];
        $belanja = collect($savings['transactions'])->firstWhere('tipe', 'belanja_kantin');
        $this->assertNotNull($belanja, 'Belanja kantin harus muncul di riwayat tabungan wali.');
        $this->assertEqualsWithDelta(8000, $belanja['nominal'], 0.001);
        $this->assertSame('bakso', $belanja['keterangan']);
        $this->assertEqualsWithDelta(42000, $savings['saldo'], 0.001);
    }
}
