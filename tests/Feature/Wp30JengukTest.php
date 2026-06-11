<?php

namespace Tests\Feature;

use App\Filament\Pages\PengajuanJengukPanel;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Livewire\Livewire;
use Modules\Kegiatan\Models\KunjunganWali;
use Modules\Kegiatan\Models\PengajuanJenguk;
use Modules\Kegiatan\Services\PenjengukanService;
use Modules\Kesantrian\Models\Santri;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-30 — Penjengukan: jadwal/kuota slot, konfirmasi otomatis vs manual,
 * walk-in saklar, akses wali (anak sendiri saja), client_ref idempoten
 * SCOPED pengaju+santri (JEBAKAN BERULANG).
 */
class Wp30JengukTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private PenjengukanService $service;

    private Santri $santri;

    private User $wali;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurePpdbTestingDatabase();
        if (! Schema::connection('pgsql')->hasTable('pengajuan_jenguk')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(array $jadwal = []): Tenant
    {
        $tenant = $this->createRuntimeTenant('wp30', 'Pesantren WP30');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        $this->service = app(PenjengukanService::class);

        $this->wali = $this->buatWali('wali');
        $this->santri = $this->buatSantri('2024001', 'Ahmad Santri');
        $this->tautkan($this->wali, $this->santri);

        if ($jadwal !== []) {
            Setting::create([
                'tenant_id' => tenant('id'),
                'key' => 'kesantrian.jadwal_penjengukan',
                'value' => $jadwal,
            ]);
        }

        return $tenant;
    }

    private function buatWali(string $prefix): User
    {
        return User::withoutGlobalScopes()->create([
            'name' => ucfirst($prefix),
            'email' => $prefix.'-'.str()->random(5).'@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);
    }

    private function buatSantri(string $nis, string $nama): Santri
    {
        return Santri::create([
            'tenant_id' => tenant('id'),
            'nama' => $nama,
            'nis' => $nis,
            'tanggal_lahir' => '2010-01-15',
            'jenis_kelamin' => 'L',
        ]);
    }

    private function tautkan(User $wali, Santri $santri): void
    {
        $wali->accessibleSantri()->attach($santri->id, [
            'tenant_id' => tenant('id'),
            'relationship_type' => 'ayah',
            'is_primary' => true,
        ]);
    }

    /** Tanggal terdekat (>= besok) yang jatuh pada hari minggu/ahad (default jadwal). */
    private function ahadDepan(): string
    {
        $t = Carbon::today()->addDay();
        while ($t->dayOfWeek !== Carbon::SUNDAY) {
            $t->addDay();
        }

        return $t->toDateString();
    }

    public function test_konfirmasi_otomatis_default(): void
    {
        $this->boot();

        $p = $this->service->ajukan($this->wali, [
            'santri_id' => $this->santri->id,
            'tanggal' => $this->ahadDepan(),
            'client_ref' => (string) str()->uuid(),
        ]);

        $this->assertSame('dikonfirmasi', $p->status);
    }

    public function test_hari_di_luar_jadwal_ditolak(): void
    {
        $this->boot(['hari' => ['ahad']]);

        // Cari hari senin terdekat (di luar jadwal ahad).
        $t = Carbon::today()->addDay();
        while ($t->dayOfWeek !== Carbon::MONDAY) {
            $t->addDay();
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('di luar jadwal penjengukan');

        $this->service->ajukan($this->wali, [
            'santri_id' => $this->santri->id,
            'tanggal' => $t->toDateString(),
        ]);
    }

    public function test_kuota_slot_penuh_ditolak_dengan_pesan(): void
    {
        $this->boot(['kuota_per_slot' => 2]);
        $tanggal = $this->ahadDepan();

        foreach ([1, 2] as $i) {
            $wali = $this->buatWali("wali{$i}");
            $santri = $this->buatSantri("20240{$i}{$i}", "Santri {$i}");
            $this->tautkan($wali, $santri);
            $this->service->ajukan($wali, ['santri_id' => $santri->id, 'tanggal' => $tanggal]);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sudah penuh');

        $this->service->ajukan($this->wali, [
            'santri_id' => $this->santri->id,
            'tanggal' => $tanggal,
        ]);
    }

    public function test_konfirmasi_otomatis_vs_manual_dua_tenant(): void
    {
        // Tenant 1 — otomatis.
        $this->boot(['konfirmasi' => 'otomatis']);
        $p1 = $this->service->ajukan($this->wali, [
            'santri_id' => $this->santri->id,
            'tanggal' => $this->ahadDepan(),
        ]);
        $this->assertSame('dikonfirmasi', $p1->status);
        tenancy()->end();

        // Tenant 2 — manual: status tetap diajukan, lalu putuskan.
        $this->boot(['konfirmasi' => 'manual']);
        $p2 = $this->service->ajukan($this->wali, [
            'santri_id' => $this->santri->id,
            'tanggal' => $this->ahadDepan(),
        ]);
        $this->assertSame('diajukan', $p2->status);

        $this->service->putuskan($p2, 'dikonfirmasi');
        $this->assertSame('dikonfirmasi', $p2->refresh()->status);
    }

    public function test_walk_in_ditolak_saat_saklar_false(): void
    {
        $this->boot(['walk_in_boleh' => false]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('walk-in');

        $this->service->walkIn($this->santri, 'Pak Budi', 'ayah');
    }

    public function test_walk_in_boleh_menulis_kunjungan_tanpa_pengajuan(): void
    {
        $this->boot(['walk_in_boleh' => true]);

        $kunjungan = $this->service->walkIn($this->santri, 'Pak Budi', 'ayah', 'Jenguk mendadak');

        $this->assertNull($kunjungan->pengajuan_id);
        $this->assertSame('hadir', $kunjungan->status);
        $this->assertSame($this->santri->id, $kunjungan->santri_id);
    }

    public function test_wali_hanya_bisa_ajukan_untuk_anak_sendiri_403(): void
    {
        $this->boot();

        $anakOrangLain = $this->buatSantri('2024099', 'Santri Lain');

        try {
            $this->service->ajukan($this->wali, [
                'santri_id' => $anakOrangLain->id,
                'tanggal' => $this->ahadDepan(),
            ]);
            $this->fail('Seharusnya AccessDeniedHttpException (403).');
        } catch (AccessDeniedHttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_client_ref_idempoten_scoped_pengaju_dan_santri(): void
    {
        $this->boot();
        $ref = (string) str()->uuid();
        $tanggal = $this->ahadDepan();

        $p1 = $this->service->ajukan($this->wali, [
            'santri_id' => $this->santri->id,
            'tanggal' => $tanggal,
            'client_ref' => $ref,
        ]);

        // Resend ref sama oleh pengaju+santri sama → record yang sama (idempoten).
        $p2 = $this->service->ajukan($this->wali, [
            'santri_id' => $this->santri->id,
            'tanggal' => $tanggal,
            'client_ref' => $ref,
        ]);
        $this->assertSame($p1->id, $p2->id);
        $this->assertSame(1, PengajuanJenguk::count());

        // JEBAKAN BERULANG: ref yang sama dipakai pengaju LAIN → 409, bukan
        // mengembalikan pengajuan milik orang lain.
        $waliLain = $this->buatWali('walilain');
        $anakLain = $this->buatSantri('2024055', 'Santri Lain');
        $this->tautkan($waliLain, $anakLain);

        try {
            $this->service->ajukan($waliLain, [
                'santri_id' => $anakLain->id,
                'tanggal' => $tanggal,
                'client_ref' => $ref,
            ]);
            $this->fail('Seharusnya ConflictHttpException (409).');
        } catch (ConflictHttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        $this->assertSame(1, PengajuanJenguk::count());
    }

    public function test_satu_santri_satu_slot_per_tanggal(): void
    {
        $this->boot();
        $tanggal = $this->ahadDepan();

        $this->service->ajukan($this->wali, [
            'santri_id' => $this->santri->id,
            'tanggal' => $tanggal,
            'client_ref' => (string) str()->uuid(),
        ]);

        // Pengajuan kedua untuk santri+tanggal sama (ref beda) → ditolak.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sudah ada pengajuan untuk santri ini');

        $this->service->ajukan($this->wali, [
            'santri_id' => $this->santri->id,
            'tanggal' => $tanggal,
            'client_ref' => (string) str()->uuid(),
        ]);
    }

    public function test_unique_index_partial_menolak_duplikat_di_level_db(): void
    {
        $this->boot();
        $tanggal = $this->ahadDepan();

        PengajuanJenguk::create([
            'tenant_id' => tenant('id'),
            'santri_id' => $this->santri->id,
            'pengaju_id' => $this->wali->id,
            'tanggal' => $tanggal,
            'status' => 'dikonfirmasi',
        ]);

        // Status batal TIDAK kena index parsial.
        PengajuanJenguk::create([
            'tenant_id' => tenant('id'),
            'santri_id' => $this->santri->id,
            'pengaju_id' => $this->wali->id,
            'tanggal' => $tanggal,
            'status' => 'batal',
        ]);

        $this->expectException(QueryException::class);

        PengajuanJenguk::create([
            'tenant_id' => tenant('id'),
            'santri_id' => $this->santri->id,
            'pengaju_id' => $this->wali->id,
            'tanggal' => $tanggal,
            'status' => 'diajukan',
        ]);
    }

    public function test_wali_batalkan_pengajuan_sendiri_dan_kuota_terbebas(): void
    {
        $this->boot(['kuota_per_slot' => 1]);
        $tanggal = $this->ahadDepan();

        $p = $this->service->ajukan($this->wali, [
            'santri_id' => $this->santri->id,
            'tanggal' => $tanggal,
        ]);

        $dibatalkan = $this->service->batal($p, $this->wali);
        $this->assertSame('batal', $dibatalkan->status);

        // Slot terbebas: wali lain bisa mengisi kuota 1 yang sama.
        $waliLain = $this->buatWali('wali-b');
        $anakLain = $this->buatSantri('2024077', 'Santri B');
        $this->tautkan($waliLain, $anakLain);

        $p2 = $this->service->ajukan($waliLain, [
            'santri_id' => $anakLain->id,
            'tanggal' => $tanggal,
        ]);
        $this->assertSame('dikonfirmasi', $p2->status);
    }

    public function test_wali_lain_tidak_bisa_batalkan_403(): void
    {
        $this->boot();

        $p = $this->service->ajukan($this->wali, [
            'santri_id' => $this->santri->id,
            'tanggal' => $this->ahadDepan(),
        ]);

        $penyusup = $this->buatWali('penyusup');

        try {
            $this->service->batal($p, $penyusup);
            $this->fail('Seharusnya AccessDeniedHttpException (403).');
        } catch (AccessDeniedHttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame('dikonfirmasi', $p->refresh()->status);
    }

    public function test_batal_pengajuan_hadir_ditolak(): void
    {
        $this->boot(['hari' => ['minggu', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu']]);

        $p = $this->service->ajukan($this->wali, [
            'santri_id' => $this->santri->id,
            'tanggal' => Carbon::today()->toDateString(),
        ]);
        $this->service->tandaiHadir($p);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tidak bisa dibatalkan');

        $this->service->batal($p->refresh(), $this->wali);
    }

    public function test_panel_pengajuan_jenguk_putuskan_manual(): void
    {
        $this->boot(['konfirmasi' => 'manual']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $p = $this->service->ajukan($this->wali, [
            'santri_id' => $this->santri->id,
            'tanggal' => $this->ahadDepan(),
        ]);
        $this->assertSame('diajukan', $p->status);

        $pengurus = $this->buatWali('pengurus');
        $pengurus->givePermissionTo('manage_santri');
        $this->actingAs($pengurus);

        Livewire::test(PengajuanJengukPanel::class)
            ->assertOk()
            ->call('putuskan', $p->id, 'dikonfirmasi');

        $this->assertSame('dikonfirmasi', $p->refresh()->status);
    }

    public function test_tandai_hadir_menulis_kunjungan_wali_reuse(): void
    {
        // Jadwal mencakup semua hari supaya "hari ini" valid.
        $this->boot(['hari' => ['minggu', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu']]);

        $p = $this->service->ajukan($this->wali, [
            'santri_id' => $this->santri->id,
            'tanggal' => Carbon::today()->toDateString(),
        ]);
        $this->assertSame('dikonfirmasi', $p->status);

        $kunjungan = $this->service->tandaiHadir($p, 'Ibu Aminah', 'ibu');

        $this->assertSame('hadir', $p->refresh()->status);
        $this->assertInstanceOf(KunjunganWali::class, $kunjungan);
        $this->assertSame($p->id, $kunjungan->pengajuan_id);
        $this->assertNotNull($kunjungan->slot);
        $this->assertSame('Ibu Aminah', $kunjungan->nama_pengunjung);
    }
}
