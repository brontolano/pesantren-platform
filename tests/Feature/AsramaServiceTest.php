<?php

namespace Tests\Feature;

use App\Filament\Resources\Asrama\BiayaAsrama\BiayaAsramaResource;
use App\Filament\Resources\Asrama\BlokAsrama\BlokAsramaResource;
use App\Filament\Resources\Asrama\KamarAsrama\KamarAsramaResource;
use App\Filament\Resources\Asrama\PenempatanAsrama\PenempatanAsramaResource;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Modules\Asrama\Models\BiayaAsrama;
use Modules\Asrama\Models\BlokAsrama;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Asrama\Models\PenempatanAsrama;
use Modules\Asrama\Services\BiayaAsramaService;
use Modules\Asrama\Services\PenempatanAsramaService;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class AsramaServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['biaya_asrama', 'permissions']);

        $this->truncatePpdbTables([
            'biaya_asrama',
            'penempatan_asrama',
            'kamar_asrama',
            'blok_asrama',
            'tagihan',
            'pembayaran',
            'tabungan_transaksi',
            'tabungan',
            'penempatan_kelas',
            'status_santri',
            'alumni',
            'santri',
            'kelas',
            'settings',
            'role_has_permissions',
            'model_has_roles',
            'model_has_permissions',
            'roles',
            'permissions',
            'users',
            'domains',
            'tenants',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_penempatan_menolak_kamar_yang_sudah_penuh(): void
    {
        [$tenant, $santriA, $santriB] = $this->createAsramaFixture('asrama-penuh');
        tenancy()->initialize($tenant);

        $kamar = $this->createKamar(1);
        $service = app(PenempatanAsramaService::class);

        $service->tempatkan($santriA, $kamar, ['tanggal_masuk' => '2026-07-01']);

        $this->expectException(ValidationException::class);

        $service->tempatkan($santriB, $kamar, ['tanggal_masuk' => '2026-07-02']);
    }

    public function test_history_penghuni_tetap_tersimpan_saat_santri_pindah_kamar(): void
    {
        [$tenant, $santri] = $this->createAsramaFixture('asrama-history');
        tenancy()->initialize($tenant);

        $kamarA = $this->createKamar(2, 'A-01');
        $kamarB = $this->createKamar(2, 'A-02');

        $service = app(PenempatanAsramaService::class);
        $service->tempatkan($santri, $kamarA, ['tanggal_masuk' => '2026-07-01']);
        $service->tempatkan($santri, $kamarB, ['tanggal_masuk' => '2026-07-10']);

        $history = PenempatanAsrama::query()->where('santri_id', $santri->id)->orderBy('tanggal_masuk')->get();

        $this->assertCount(2, $history);
        $this->assertFalse((bool) $history->first()->is_current);
        $this->assertTrue((bool) $history->last()->is_current);
        $this->assertSame($kamarA->id, $history->first()->kamar_id);
        $this->assertSame($kamarB->id, $history->last()->kamar_id);
    }

    public function test_occupancy_rate_dihitung_akurat_per_tenant(): void
    {
        [$tenant, $santriA, $santriB] = $this->createAsramaFixture('asrama-occupancy');
        tenancy()->initialize($tenant);

        $kamarA = $this->createKamar(2, 'B-01');
        $kamarB = $this->createKamar(2, 'B-02');

        $service = app(PenempatanAsramaService::class);
        $service->tempatkan($santriA, $kamarA, ['tanggal_masuk' => '2026-07-01']);
        $service->tempatkan($santriB, $kamarB, ['tanggal_masuk' => '2026-07-01']);

        $summary = $service->occupancySummary();

        $this->assertSame(2, $summary['total_kamar']);
        $this->assertSame(4, $summary['total_kapasitas']);
        $this->assertSame(2, $summary['total_penghuni']);
        $this->assertSame(50.0, $summary['occupancy_rate']);
    }

    public function test_biaya_asrama_terhubung_ke_tagihan_keuangan(): void
    {
        [$tenant, $santri] = $this->createAsramaFixture('asrama-biaya');
        tenancy()->initialize($tenant);

        $kamar = $this->createKamar(2, 'C-01', 350000);

        $biaya = app(BiayaAsramaService::class)->sinkronkan([
            'santri_id' => $santri->id,
            'kamar_id' => $kamar->id,
            'periode' => '2026-07',
            'nominal' => 350000,
            'status_pembayaran' => 'belum_bayar',
            'jatuh_tempo' => '2026-07-10',
        ]);

        $this->assertInstanceOf(BiayaAsrama::class, $biaya);
        $this->assertNotNull($biaya->keuangan_tagihan_id);
        $this->assertSame('asrama', $biaya->tagihanKeuangan->jenis_tagihan);
    }

    public function test_resource_dan_route_asrama_terdaftar_untuk_admin_pesantren(): void
    {
        [$tenant] = $this->createAsramaFixture('asrama-route');
        tenancy()->initialize($tenant);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Asrama',
            'email' => 'admin-asrama@test.local',
            'password' => 'password',
        ]);
        $admin->assignRole('admin_pesantren');

        $this->actingAs($admin);

        $this->assertTrue(BlokAsramaResource::canAccess());
        $this->assertTrue(KamarAsramaResource::canAccess());
        $this->assertTrue(PenempatanAsramaResource::canAccess());
        $this->assertTrue(BiayaAsramaResource::canAccess());
        $this->assertTrue(Route::has('filament.admin.resources.asrama.blok-asrama.blok-asramas.index'));
        $this->assertTrue(Route::has('filament.admin.resources.asrama.kamar-asrama.kamar-asramas.index'));
        $this->assertTrue(Route::has('filament.admin.resources.asrama.penempatan-asrama.penempatan-asramas.index'));
        $this->assertTrue(Route::has('filament.admin.resources.asrama.biaya-asrama.biaya-asramas.index'));
    }

    public function test_isolasi_tenant_penempatan_asrama_tetap_aman(): void
    {
        [$tenantA, $santriA] = $this->createAsramaFixture('asrama-tenant-a');
        tenancy()->initialize($tenantA);
        $kamarA = $this->createKamar(2, 'D-01');
        app(PenempatanAsramaService::class)->tempatkan($santriA, $kamarA, ['tanggal_masuk' => '2026-07-01']);

        [$tenantB] = $this->createAsramaFixture('asrama-tenant-b');
        tenancy()->initialize($tenantB);
        $this->createKamar(2, 'E-01');

        $placements = PenempatanAsrama::query()->get();
        $rooms = KamarAsrama::query()->get();
        $blocks = BlokAsrama::query()->get();

        $this->assertCount(0, $placements);
        $this->assertCount(1, $rooms);
        $this->assertCount(1, $blocks);
    }

    /**
     * @return array{0: \App\Models\Tenant, 1?: Santri, 2?: Santri}
     */
    private function createAsramaFixture(string $tenantId): array
    {
        $tenant = $this->createTestTenant($tenantId, 'Pesantren ' . $tenantId, $tenantId);
        tenancy()->initialize($tenant);

        $kelas = Kelas::create([
            'tenant_id' => $tenant->id,
            'nama_kelas' => 'VII-A',
            'tingkat' => '7',
            'capacity' => 32,
            'is_active' => true,
        ]);

        $santriA = Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => strtoupper(substr($tenantId, 0, 6)) . '01',
            'nama' => 'Santri ' . $tenantId . ' A',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => $kelas->id,
        ]);

        $santriB = Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => strtoupper(substr($tenantId, 0, 6)) . '02',
            'nama' => 'Santri ' . $tenantId . ' B',
            'tanggal_lahir' => '2012-02-02',
            'jenis_kelamin' => 'P',
            'status' => 'aktif',
            'kelas_id' => $kelas->id,
        ]);

        return [$tenant, $santriA, $santriB];
    }

    private function createKamar(int $kapasitas, string $nomor = 'A-01', int $biaya = 300000): KamarAsrama
    {
        $blok = BlokAsrama::query()->first() ?? BlokAsrama::create([
            'nama_blok' => 'Blok A',
            'lokasi' => 'Gedung Putra',
            'jumlah_kamar' => 5,
            'is_active' => true,
        ]);

        return KamarAsrama::create([
            'blok_id' => $blok->id,
            'nomor_kamar' => $nomor,
            'kapasitas' => $kapasitas,
            'tipe' => 'regular',
            'biaya_per_bulan' => $biaya,
            'is_active' => true,
        ]);
    }
}
