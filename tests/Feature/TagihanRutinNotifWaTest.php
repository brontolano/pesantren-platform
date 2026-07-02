<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Models\TagihanRutin;
use Modules\Keuangan\Services\TagihanRutinService;
use Modules\Notifikasi\Services\NotifDispatcher;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * #339 — Notif WA otomatis ke wali saat Tagihan Rutin terbit (notif_wa=true).
 *
 * Kontrak yang dikunci:
 *  (a) terbit dengan notif_wa=true → kirim event 'tagihan_terbit' TEPAT SEKALI
 *      per tagihan baru + penanda notif_wa_sent_at terisi bila terkirim;
 *  (b) run kedua (bahkan --force) TIDAK kirim ulang;
 *  (c) notif_wa=false → tidak ada kirim;
 *  (d) dispatcher/gateway meledak → penerbitan tagihan TETAP sukses.
 */
class TagihanRutinNotifWaTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private MockInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'santri', 'tagihan', 'tagihan_rutin',
        ]);
        $this->truncatePpdbTables([
            'tagihan_rutin', 'tagihan', 'santri', 'domains', 'tenants',
        ]);

        $this->dispatcher = Mockery::mock(NotifDispatcher::class);
        $this->app->instance(NotifDispatcher::class, $this->dispatcher);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    private function boot(string $prefix, bool $notifWa, ?string $hpWali = '081234567890'): TagihanRutin
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        Santri::create([
            'nis' => strtoupper($prefix).'-001',
            'nama' => 'Santri '.strtoupper($prefix),
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'no_hp_wali' => $hpWali,
        ]);

        return TagihanRutin::create([
            'nama' => 'SPP Bulanan',
            'jenis_tagihan' => 'spp',
            'frekuensi' => 'bulanan',
            'nominal' => 350000,
            'target_tipe' => 'semua',
            'tanggal_terbit' => 1,
            'tempo_hari' => 10,
            'notif_wa' => $notifWa,
            'aktif' => true,
        ]);
    }

    public function test_terbit_dengan_notif_wa_true_kirim_sekali_dan_menandai_tagihan(): void
    {
        $aturan = $this->boot('trn1', notifWa: true);

        $this->dispatcher->shouldReceive('kirim')
            ->once()
            ->with('tagihan_terbit', Mockery::on(function (array $p): bool {
                return in_array('6281234567890', $p['wa'] ?? [], true)
                    && ($p['data']['nama_santri'] ?? null) === 'Santri TRN1'
                    && ($p['data']['jenis_tagihan'] ?? null) === 'spp'
                    && ($p['data']['nominal'] ?? null) === '350.000'
                    && filled($p['data']['jatuh_tempo'] ?? null);
            }))
            ->andReturn(['event' => 'tagihan_terbit', 'channel' => 'wa', 'terkirim' => 1, 'gagal' => 0]);

        $hasil = (new TagihanRutinService)->terbitkan($aturan, '2026-07');

        $this->assertSame(1, $hasil['dibuat']);
        $tagihan = Tagihan::query()->firstOrFail();
        $this->assertNotNull($tagihan->notif_wa_sent_at, 'Penanda idempotensi harus terisi setelah terkirim.');
    }

    public function test_run_kedua_tidak_kirim_ulang(): void
    {
        $aturan = $this->boot('trn2', notifWa: true);

        $this->dispatcher->shouldReceive('kirim')
            ->once() // TOTAL sekali walau terbitkan dipanggil 3x
            ->andReturn(['event' => 'tagihan_terbit', 'channel' => 'wa', 'terkirim' => 1, 'gagal' => 0]);

        $svc = new TagihanRutinService;
        $svc->terbitkan($aturan, '2026-07');
        $svc->terbitkan($aturan->fresh(), '2026-07');            // lapis 1: aturan sudah terbit
        $svc->terbitkan($aturan->fresh(), '2026-07', force: true); // lapis 2: tagihan sudah ada

        $this->assertSame(1, Tagihan::query()->count());
    }

    public function test_notif_wa_false_tidak_kirim(): void
    {
        $aturan = $this->boot('trn3', notifWa: false);

        $this->dispatcher->shouldNotReceive('kirim');

        $hasil = (new TagihanRutinService)->terbitkan($aturan, '2026-07');

        $this->assertSame(1, $hasil['dibuat']);
        $this->assertNull(Tagihan::query()->firstOrFail()->notif_wa_sent_at);
    }

    public function test_gagal_gateway_tidak_menggagalkan_penerbitan(): void
    {
        $aturan = $this->boot('trn4', notifWa: true);

        $this->dispatcher->shouldReceive('kirim')
            ->andThrow(new \RuntimeException('gateway down'));

        $hasil = (new TagihanRutinService)->terbitkan($aturan, '2026-07'); // TIDAK boleh throw

        $this->assertSame(1, $hasil['dibuat']);
        $tagihan = Tagihan::query()->firstOrFail();
        // Gagal kirim → penanda TIDAK diisi (log jujur), tapi tagihan tetap terbit.
        $this->assertNull($tagihan->notif_wa_sent_at);
        $this->assertSame('2026-07', $aturan->fresh()->terakhir_terbit_periode);
    }

    public function test_gateway_hijau_palsu_nol_terkirim_tidak_menandai_sent(): void
    {
        $aturan = $this->boot('trn5', notifWa: true);

        // Gateway "hijau palsu" (jebakan WAHA) — dispatcher jujur: 0 terkirim.
        $this->dispatcher->shouldReceive('kirim')
            ->once()
            ->andReturn(['event' => 'tagihan_terbit', 'channel' => 'wa', 'terkirim' => 0, 'gagal' => 1]);

        (new TagihanRutinService)->terbitkan($aturan, '2026-07');

        $this->assertNull(Tagihan::query()->firstOrFail()->notif_wa_sent_at);
    }

    public function test_santri_tanpa_nomor_wali_dilewati_tanpa_error(): void
    {
        $aturan = $this->boot('trn6', notifWa: true, hpWali: null);

        $this->dispatcher->shouldNotReceive('kirim');

        $hasil = (new TagihanRutinService)->terbitkan($aturan, '2026-07');

        $this->assertSame(1, $hasil['dibuat']);
    }
}
