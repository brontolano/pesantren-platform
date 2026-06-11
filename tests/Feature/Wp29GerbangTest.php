<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kegiatan\Models\LogGerbang;
use Modules\Kegiatan\Services\GerbangService;
use Modules\Kesantrian\Models\Santri;
use Modules\Notifikasi\Services\NotifDispatcher;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-29 — Pos Gerbang: status izin, catat keluar/masuk, sinkron tandaiKembali
 * (WP-26), notif keluar tanpa izin, cache izin aktif.
 */
class Wp29GerbangTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private GerbangService $service;

    private Santri $santri;

    private User $petugas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurePpdbTestingDatabase();
        if (! Schema::connection('pgsql')->hasTable('log_gerbang')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(): void
    {
        $tenant = $this->createRuntimeTenant('wp29', 'Pesantren WP29');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        $this->service = app(GerbangService::class);

        $this->petugas = User::withoutGlobalScopes()->create([
            'name' => 'Petugas Keamanan',
            'email' => 'satpam@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);

        $this->santri = Santri::create([
            'tenant_id' => tenant('id'),
            'nama' => 'Ahmad Santri',
            'nis' => '2024001',
            'tanggal_lahir' => '2010-01-15',
            'jenis_kelamin' => 'L',
        ]);
    }

    public function test_notif_tanpa_izin_tidak_spam_dalam_10_menit(): void
    {
        $this->boot();

        // Scan ganda santri yang sama dalam 10 menit → notif HANYA sekali.
        $dispatcher = $this->mock(NotifDispatcher::class);
        $dispatcher->shouldReceive('kirim')
            ->once()
            ->andReturn(['event' => 'gerbang_tanpa_izin', 'channel' => null, 'terkirim' => 0, 'gagal' => 0]);

        $log1 = $this->service->catat($this->santri, 'keluar', $this->petugas);
        $log2 = $this->service->catat($this->santri, 'keluar', $this->petugas);

        // Log tetap tercatat dua kali — hanya notifnya yang diredam.
        $this->assertTrue($log1->is_tanpa_izin);
        $this->assertTrue($log2->is_tanpa_izin);
        $this->assertSame(2, LogGerbang::count());
    }

    public function test_scan_santri_non_aktif_tidak_ditemukan(): void
    {
        $this->boot();
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $this->santri->update(['status' => 'lulus']); // alumni

        $this->petugas->givePermissionTo('akses_pwa_guru');
        Auth::login($this->petugas);

        $host = tenant()->domains()->first()->domain;
        $request = Request::create("http://{$host}/guru/gerbang?nis=2024001", 'GET');
        $request->headers->set('Host', $host);
        $response = app(Kernel::class)->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('tidak_ditemukan', $response->getContent());
        $this->assertStringNotContainsString('Ahmad Santri', $response->getContent());
    }

    private function buatIzinDisetujui(Carbon $mulai, Carbon $selesai): IzinSantri
    {
        return IzinSantri::create([
            'tenant_id' => tenant('id'),
            'santri_id' => $this->santri->id,
            'jenis_izin' => 'pulang',
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_selesai' => $selesai->toDateString(),
            'alasan' => 'Acara keluarga',
            'status' => 'disetujui',
        ]);
    }

    public function test_keluar_tanpa_izin_log_merah_dan_notif_dispatched(): void
    {
        $this->boot();

        $dispatcher = $this->mock(NotifDispatcher::class);
        $dispatcher->shouldReceive('kirim')
            ->once()
            ->withArgs(fn (string $event, array $payload): bool => $event === 'gerbang_tanpa_izin'
                && $payload['data']['nama_santri'] === 'Ahmad Santri')
            ->andReturn(['event' => 'gerbang_tanpa_izin', 'channel' => null, 'terkirim' => 0, 'gagal' => 0]);

        $kondisi = $this->service->statusSantri($this->santri);
        $this->assertSame('merah', $kondisi['status']);

        $log = $this->service->catat($this->santri, 'keluar', $this->petugas);

        $this->assertTrue($log->is_tanpa_izin);
        $this->assertNull($log->izin_id);
        $this->assertSame('keluar', $log->arah);
    }

    public function test_masuk_dengan_izin_sinkron_tandai_kembali(): void
    {
        $this->boot();

        $izin = $this->buatIzinDisetujui(Carbon::today()->subDay(), Carbon::today()->addDay());

        $kondisi = $this->service->statusSantri($this->santri);
        $this->assertSame('hijau', $kondisi['status']);
        $this->assertSame($izin->id, $kondisi['izin']->id);

        $log = $this->service->catat($this->santri, 'masuk', $this->petugas);

        $this->assertFalse($log->is_tanpa_izin);
        $this->assertSame($izin->id, $log->izin_id);
        $this->assertTrue($izin->refresh()->sudah_kembali);
        $this->assertNotNull($izin->tanggal_kembali_aktual);
    }

    public function test_keluar_dengan_izin_aktif_tidak_tanpa_izin(): void
    {
        $this->boot();

        $izin = $this->buatIzinDisetujui(Carbon::today(), Carbon::today()->addDays(2));

        $log = $this->service->catat($this->santri, 'keluar', $this->petugas);

        $this->assertFalse($log->is_tanpa_izin);
        $this->assertSame($izin->id, $log->izin_id);
        $this->assertFalse($izin->refresh()->sudah_kembali);
    }

    public function test_status_kuning_terlambat_kembali(): void
    {
        $this->boot();

        $izin = $this->buatIzinDisetujui(Carbon::today()->subDays(5), Carbon::today()->subDays(2));

        $kondisi = $this->service->statusSantri($this->santri);

        $this->assertSame('kuning', $kondisi['status']);
        $this->assertSame($izin->id, $kondisi['izin']->id);

        // Masuk saat terlambat tetap menandai kembali (sinkron WP-26).
        $this->service->catat($this->santri, 'masuk', $this->petugas);
        $this->assertTrue($izin->refresh()->sudah_kembali);
    }

    public function test_izin_aktif_hari_ini_untuk_cache_offline(): void
    {
        $this->boot();

        $this->buatIzinDisetujui(Carbon::today(), Carbon::today()->addDay());

        $daftar = $this->service->izinAktifHariIni();

        $this->assertCount(1, $daftar);
        $this->assertSame('2024001', $daftar[0]['nis']);
        $this->assertFalse($daftar[0]['terlambat']);

        $logCount = LogGerbang::count();
        $this->assertSame(0, $logCount); // endpoint read-only
    }
}
