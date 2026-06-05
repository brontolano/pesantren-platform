<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\MetodePembayaran;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\TabunganTopup;
use Modules\Keuangan\Models\TabunganTransaksi;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class KeuanganModelTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants',
            'domains',
            'santri',
            'tabungan',
            'tabungan_transaksi',
            'metode_pembayaran',
            'tabungan_topup',
            'pembayaran',
        ]);

        $this->truncatePpdbTables([
            'tabungan_topup',
            'metode_pembayaran',
            'tabungan_transaksi',
            'tabungan',
            'pembayaran',
            'tagihan',
            'santri',
            'kelas',
            'domains',
            'tenants',
            'users',
        ]);
    }

    public function test_tabungan_dan_transaksi_terikat_ke_tenant_aktif(): void
    {
        $tenant = $this->createRuntimeTenant('tenant-keuangan', 'Tenant Keuangan');

        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        $santri = Santri::create([
            'nis' => 'KEU001',
            'nama' => 'Santri Keuangan',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);

        $tabungan = Tabungan::create([
            'santri_id' => $santri->id,
            'saldo' => 150000,
        ]);

        $transaksi = TabunganTransaksi::create([
            'tabungan_id' => $tabungan->id,
            'tipe' => 'setor',
            'nominal' => 50000,
            'saldo_sebelum' => 100000,
            'saldo_sesudah' => 150000,
            'tanggal' => '2026-06-03',
        ]);

        $this->assertSame($tenant->id, $tabungan->tenant_id);
        $this->assertSame($tenant->id, $transaksi->tenant_id);
        $this->assertTrue($tabungan->santri->is($santri));
        $this->assertTrue($transaksi->tabungan->is($tabungan));

        tenancy()->end();
    }

    public function test_schema_w0_pwa_wali_hanya_menambah_tabel_tenant_keuangan(): void
    {
        $tenant = $this->createRuntimeTenant('tenant-pwa-wali', 'Tenant PWA Wali');

        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        $this->assertTrue(Schema::hasTable('metode_pembayaran'));
        $this->assertTrue(Schema::hasTable('tabungan_topup'));
        $this->assertTrue(Schema::hasColumns('pembayaran', ['alasan_penolakan']));
        $this->assertTrue(Schema::hasColumns('tabungan', ['limit_harian', 'is_frozen']));

        tenancy()->end();
    }

    public function test_metode_pembayaran_dan_topup_terikat_ke_tenant_aktif(): void
    {
        $tenant = $this->createRuntimeTenant('tenant-pwa-model', 'Tenant PWA Model');

        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        $santri = Santri::create([
            'nis' => 'KEU002',
            'nama' => 'Santri PWA',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'P',
            'status' => 'aktif',
        ]);

        $tabungan = Tabungan::create([
            'santri_id' => $santri->id,
            'saldo' => 250000,
            'limit_harian' => 50000,
            'is_frozen' => false,
        ]);

        $metode = MetodePembayaran::create([
            'tipe' => 'bank',
            'nama' => 'BSI Utama',
            'nomor' => '1234567890',
            'atas_nama' => 'Yayasan Asy-Syifaa',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $topup = TabunganTopup::create([
            'tabungan_id' => $tabungan->id,
            'nominal' => 100000,
            'tanggal_transfer' => '2026-06-05',
            'nama_pengirim' => 'Wali Santri',
            'bukti' => 'topup/bukti.jpg',
            'status' => 'menunggu',
        ]);

        $this->assertSame($tenant->id, $metode->tenant_id);
        $this->assertSame($tenant->id, $topup->tenant_id);
        $this->assertTrue($topup->tabungan->is($tabungan));
        $this->assertCount(1, $tabungan->topupRequests);

        tenancy()->end();
    }

    private function seedTenantRow(Tenant $tenant): void
    {
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            [
                'name' => $tenant->name,
                'is_active' => $tenant->is_active,
                'data' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function createRuntimeTenant(string $prefix, string $name): Tenant
    {
        $id = sprintf('%s-%s', $prefix, str()->lower(str()->random(6)));

        $tenant = Tenant::create([
            'id' => $id,
            'name' => $name,
            'is_active' => true,
        ]);
        $tenant->domains()->create(['domain' => $id]);

        return $tenant;
    }
}
