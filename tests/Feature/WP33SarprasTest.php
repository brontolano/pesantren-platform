<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\Pembiayaan\Models\AreaPiket;
use Modules\Pembiayaan\Models\CeklisPiket;
use Modules\Pembiayaan\Models\LaporanKerusakan;
use Modules\Pembiayaan\Services\SarprasService;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-33 — Lapor Kerusakan & Piket (Sarpras).
 *
 * (a) Lapor kerusakan via PWA: foto WAJIB pdf/jpg/png — upload html/exe DITOLAK
 *     (stored-XSS), file tersimpan di dir 'kerusakan'.
 * (b) /_tenant-storage/kerusakan/* WAJIB login (direktori sensitif).
 * (c) Siklus status SEARAH masuk → dikerjakan → selesai; mundur/lompat ditolak;
 *     biaya hanya info (tidak auto-posting).
 * (d) Ceklis piket: scan QR area; QR tak dikenal ditolak; scan ulang
 *     petugas+area+hari yang sama = update (bukan baris baru).
 */
class WP33SarprasTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private SarprasService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SarprasService;
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'laporan_kerusakan', 'area_piket', 'ceklis_piket']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    private function boot(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new RolesAndPermissionsSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $tenant;
    }

    private function buatGuru(): User
    {
        $u = User::withoutGlobalScopes()->create([
            'name' => 'Guru Piket',
            'email' => 'pkt'.str()->random(8).'@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);
        $u->givePermissionTo('akses_pwa_guru');

        return $u;
    }

    // ── (a) lapor kerusakan + validasi foto ─────────────────────────────────

    public function test_lapor_kerusakan_dengan_foto_png_tersimpan_di_dir_kerusakan(): void
    {
        Storage::fake('public');
        $this->boot('sp1');
        $guru = $this->buatGuru();

        $res = $this->actingAs($guru)->post('/api/pwa/guru/sarpras/lapor-kerusakan', [
            'lokasi' => 'Kamar Mandi Blok B',
            'deskripsi' => 'Keran bocor deras',
            'foto' => UploadedFile::fake()->createWithContent('keran.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')),
        ], ['Accept' => 'application/json']);

        $res->assertStatus(201);
        $laporan = LaporanKerusakan::firstOrFail();
        $this->assertSame('masuk', $laporan->status);
        $this->assertSame($guru->id, $laporan->pelapor_id);
        $this->assertStringStartsWith('kerusakan/', $laporan->foto_path);
        $this->assertStringStartsWith('/_tenant-storage/kerusakan/', $laporan->fotoUrl());
        Storage::disk('public')->assertExists($laporan->foto_path);
    }

    public function test_upload_html_ditolak_stored_xss(): void
    {
        Storage::fake('public');
        $this->boot('sp2');
        $guru = $this->buatGuru();

        $res = $this->actingAs($guru)->post('/api/pwa/guru/sarpras/lapor-kerusakan', [
            'lokasi' => 'Aula',
            'deskripsi' => 'Percobaan XSS',
            'foto' => UploadedFile::fake()->createWithContent('jahat.html', '<script>alert(1)</script>'),
        ], ['Accept' => 'application/json']);

        $res->assertStatus(422);
        $this->assertSame(0, LaporanKerusakan::count());
    }

    public function test_foto_wajib_tanpa_foto_ditolak(): void
    {
        $this->boot('sp3');
        $guru = $this->buatGuru();

        $res = $this->actingAs($guru)->postJson('/api/pwa/guru/sarpras/lapor-kerusakan', [
            'lokasi' => 'Dapur',
            'deskripsi' => 'Kompor rusak',
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['foto']);
    }

    // ── (b) /_tenant-storage/kerusakan gated login ──────────────────────────

    public function test_tenant_storage_kerusakan_tanpa_login_403(): void
    {
        $tenant = $this->boot('sp4');
        tenancy()->end();

        $host = $tenant->domains()->first()->domain;
        $request = Request::create("http://{$host}/_tenant-storage/kerusakan/foto-rahasia.png");
        $request->headers->set('Host', $host);

        $response = app(Kernel::class)->handle($request);
        $this->assertSame(403, $response->getStatusCode(), 'Foto kerusakan tanpa login WAJIB 403');
    }

    // ── (c) siklus status ───────────────────────────────────────────────────

    private function buatLaporan(User $pelapor): LaporanKerusakan
    {
        return LaporanKerusakan::create([
            'tenant_id' => tenant('id'),
            'pelapor_id' => $pelapor->id,
            'lokasi' => 'Kelas 1A',
            'deskripsi' => 'Pintu lepas engsel',
            'foto_path' => 'kerusakan/x.png',
            'status' => 'masuk',
        ]);
    }

    public function test_siklus_status_masuk_dikerjakan_selesai(): void
    {
        $this->boot('sp5');
        $guru = $this->buatGuru();
        $laporan = $this->buatLaporan($guru);

        $laporan = $this->service->ubahStatus($laporan, 'dikerjakan');
        $this->assertSame('dikerjakan', $laporan->status);

        $laporan = $this->service->ubahStatus($laporan, 'selesai', ['biaya' => 150000, 'catatan' => 'Ganti engsel']);
        $this->assertSame('selesai', $laporan->status);
        $this->assertSame('150000.00', (string) $laporan->biaya);
        $this->assertSame('Ganti engsel', $laporan->catatan);
    }

    public function test_transisi_lompat_dan_mundur_ditolak(): void
    {
        $this->boot('sp6');
        $guru = $this->buatGuru();
        $laporan = $this->buatLaporan($guru);

        // Lompat masuk → selesai: tolak.
        try {
            $this->service->ubahStatus($laporan, 'selesai');
            $this->fail('Transisi masuk → selesai HARUS ditolak');
        } catch (InvalidArgumentException) {
        }

        // Mundur dikerjakan → masuk: tolak.
        $laporan = $this->service->ubahStatus($laporan->fresh(), 'dikerjakan');
        $this->expectException(InvalidArgumentException::class);
        $this->service->ubahStatus($laporan, 'masuk');
    }

    // ── (d) ceklis piket via scan QR ────────────────────────────────────────

    public function test_scan_piket_per_area_tercatat(): void
    {
        $this->boot('sp7');
        $guru = $this->buatGuru();
        $area = AreaPiket::create(['tenant_id' => tenant('id'), 'nama' => 'Masjid', 'kode_qr' => 'PIKET-MASJID', 'aktif' => true]);

        $res = $this->actingAs($guru)->postJson('/api/pwa/guru/sarpras/piket/scan', [
            'kode_qr' => 'PIKET-MASJID',
            'status' => 'bersih',
        ]);

        $res->assertStatus(201);
        $ceklis = CeklisPiket::firstOrFail();
        $this->assertSame($area->id, $ceklis->area_id);
        $this->assertSame($guru->id, $ceklis->petugas_id);
        $this->assertSame('bersih', $ceklis->status);
    }

    public function test_scan_ulang_hari_sama_update_bukan_duplikat(): void
    {
        $this->boot('sp8');
        $guru = $this->buatGuru();
        AreaPiket::create(['tenant_id' => tenant('id'), 'nama' => 'Aula', 'kode_qr' => 'PIKET-AULA', 'aktif' => true]);

        $a = $this->service->scanPiket($guru, 'PIKET-AULA', 'kotor', 'banyak sampah');
        $b = $this->service->scanPiket($guru, 'PIKET-AULA', 'bersih', 'sudah dibersihkan');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, CeklisPiket::count());
        $this->assertSame('bersih', $b->status);
    }

    public function test_scan_qr_tak_dikenal_atau_nonaktif_ditolak(): void
    {
        $this->boot('sp9');
        $guru = $this->buatGuru();
        AreaPiket::create(['tenant_id' => tenant('id'), 'nama' => 'Gudang', 'kode_qr' => 'PIKET-GUDANG', 'aktif' => false]);

        $this->actingAs($guru)->postJson('/api/pwa/guru/sarpras/piket/scan', [
            'kode_qr' => 'PIKET-TIDAK-ADA', 'status' => 'bersih',
        ])->assertStatus(422);

        $this->actingAs($guru)->postJson('/api/pwa/guru/sarpras/piket/scan', [
            'kode_qr' => 'PIKET-GUDANG', 'status' => 'bersih',
        ])->assertStatus(422);
    }

    public function test_ringkasan_piket_hari_ini_per_area(): void
    {
        $this->boot('sp10');
        $guru = $this->buatGuru();
        AreaPiket::create(['tenant_id' => tenant('id'), 'nama' => 'Masjid', 'kode_qr' => 'QR-1', 'aktif' => true]);
        AreaPiket::create(['tenant_id' => tenant('id'), 'nama' => 'Aula', 'kode_qr' => 'QR-2', 'aktif' => true]);
        $this->service->scanPiket($guru, 'QR-1', 'rusak', 'keran patah');

        $ringkasan = $this->service->piketHariIni();

        $this->assertCount(2, $ringkasan);
        $perNama = collect($ringkasan)->keyBy(fn ($r) => $r['area']->nama);
        $this->assertSame('rusak', $perNama['Masjid']['ceklis']->first()->status);
        $this->assertCount(0, $perNama['Aula']['ceklis']);
    }
}
