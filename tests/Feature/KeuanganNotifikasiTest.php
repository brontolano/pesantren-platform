<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\PembayaranService;
use Modules\Keuangan\Services\TopupService;
use Modules\Notifikasi\Services\NotifDispatcher;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * NOTIF-1/TGH-5/TAB-4 — wiring notif WA event uang via NotifDispatcher (F7).
 * Spy memastikan event terpancar SETELAH operasi sukses, dan TIDAK menggagalkan
 * alur uang (fire-and-forget).
 */
class KeuanganNotifikasiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private MockInterface $dispatcher;

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

        $this->dispatcher = Mockery::spy(NotifDispatcher::class);
        $this->app->instance(NotifDispatcher::class, $this->dispatcher);
    }

    private int $userId;

    /** @return array{0:Santri,1:Tabungan} */
    private function boot(string $prefix, float $saldo = 0): array
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        $this->userId = (int) User::withoutGlobalScopes()->create([
            'name' => 'Bendahara', 'email' => $prefix.'@uji.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ])->id;

        $santri = Santri::create([
            'nis' => strtoupper($prefix).'-001', 'nama' => 'Santri Notif',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
            'no_hp_wali' => '081234567890',
        ]);
        $tabungan = Tabungan::create(['santri_id' => $santri->id, 'saldo' => $saldo]);

        return [$santri, $tabungan];
    }

    public function test_verifikasi_dan_tolak_pembayaran_memancarkan_notif(): void
    {
        [$santri] = $this->boot('not1');
        $tagihan = Tagihan::create([
            'santri_id' => $santri->id, 'jenis_tagihan' => 'SPP', 'nominal' => 100000,
            'nominal_akhir' => 100000, 'status' => 'belum_dibayar',
            'jatuh_tempo' => now()->addDays(7)->toDateString(),
        ]);
        $b1 = Pembayaran::create([
            'tagihan_id' => $tagihan->id, 'santri_id' => $santri->id, 'nominal' => 60000,
            'metode' => 'transfer', 'tanggal_pembayaran' => now()->toDateString(), 'is_verified' => false,
        ]);
        $b2 = Pembayaran::create([
            'tagihan_id' => $tagihan->id, 'santri_id' => $santri->id, 'nominal' => 30000,
            'metode' => 'transfer', 'tanggal_pembayaran' => now()->toDateString(), 'is_verified' => false,
        ]);

        $svc = app(PembayaranService::class);
        $svc->verifikasiPembayaran($b1, $this->userId);
        $svc->tolakPembayaran($b2, $this->userId, 'Bukti buram');

        $this->dispatcher->shouldHaveReceived('kirim')
            ->with('pembayaran_diverifikasi', Mockery::on(fn ($p) => in_array('081234567890', $p['wa'] ?? [], true)))
            ->once();
        $this->dispatcher->shouldHaveReceived('kirim')
            ->with('pembayaran_ditolak', Mockery::on(fn ($p) => ($p['data']['alasan'] ?? null) === 'Bukti buram'))
            ->once();
        tenancy()->end();
    }

    public function test_topup_setujui_dan_tolak_memancarkan_notif(): void
    {
        [, $tabungan] = $this->boot('not2');
        $svc = app(TopupService::class);
        $t1 = $svc->ajukan($tabungan, ['nominal' => 50000, 'tanggal_transfer' => '2026-06-10', 'nama_pengirim' => 'Wali', 'bukti' => 'b.jpg']);
        $t2 = $svc->ajukan($tabungan, ['nominal' => 25000, 'tanggal_transfer' => '2026-06-10', 'nama_pengirim' => 'Wali', 'bukti' => 'b.jpg']);

        $svc->setujui($t1, $this->userId);
        $svc->tolak($t2, $this->userId, 'Nominal tak cocok');

        $this->dispatcher->shouldHaveReceived('kirim')->with('topup_diverifikasi', Mockery::any())->once();
        $this->dispatcher->shouldHaveReceived('kirim')->with('topup_ditolak', Mockery::any())->once();
        // Saldo benar-benar berubah (notif tak mengganggu alur uang).
        $this->assertEqualsWithDelta(50000, (float) $tabungan->fresh()->saldo, 0.01);
        tenancy()->end();
    }

    public function test_dispatcher_meledak_tidak_menggagalkan_alur_uang(): void
    {
        [, $tabungan] = $this->boot('not3');
        $this->dispatcher->shouldReceive('kirim')->andThrow(new \RuntimeException('gateway down'));

        $svc = app(TopupService::class);
        $topup = $svc->ajukan($tabungan, ['nominal' => 40000, 'tanggal_transfer' => '2026-06-10', 'nama_pengirim' => 'Wali', 'bukti' => 'b.jpg']);
        $hasil = $svc->setujui($topup, $this->userId); // TIDAK boleh throw

        $this->assertSame('disetujui', $hasil->status);
        $this->assertEqualsWithDelta(40000, (float) $tabungan->fresh()->saldo, 0.01);
        tenancy()->end();
    }

    private function seedTenantRow(Tenant $tenant): void
    {
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => $tenant->is_active,
                'data' => json_encode([]), 'created_at' => now(), 'updated_at' => now()],
        );
    }
}
