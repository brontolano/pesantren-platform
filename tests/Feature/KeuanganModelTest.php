<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\TabunganTransaksi;
use Tests\TestCase;

class KeuanganModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabungan_dan_transaksi_terikat_ke_tenant_aktif(): void
    {
        $tenant = Tenant::create(['id' => 'tenant-keuangan', 'name' => 'Tenant Keuangan']);
        $tenant->domains()->create(['domain' => 'tenant-keuangan']);

        tenancy()->initialize($tenant);

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

        $this->assertSame('tenant-keuangan', $tabungan->tenant_id);
        $this->assertSame('tenant-keuangan', $transaksi->tenant_id);
        $this->assertTrue($tabungan->santri->is($santri));
        $this->assertTrue($transaksi->tabungan->is($tabungan));

        tenancy()->end();
    }
}
