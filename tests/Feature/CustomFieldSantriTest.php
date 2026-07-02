<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\CustomFieldRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Custom Field per-tenant (pilot Santri) — definisi via Setting, nilai di kolom
 * JSON santri.custom_fields, render dinamis via CustomFieldRegistry.
 */
class CustomFieldSantriTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'santri', 'settings']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function bootTenant(): void
    {
        $tenant = $this->createRuntimeTenant('cf', 'Pesantren CF');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    public function test_kolom_custom_fields_ada_dan_ter_cast_array(): void
    {
        $this->bootTenant();

        $this->assertTrue(
            Schema::connection('tenant')->hasColumn('santri', 'custom_fields'),
            'Kolom santri.custom_fields harus ada (migrasi additif).',
        );

        tenancy()->end();
    }

    public function test_definisi_via_setting_dibaca_registry_dan_bangun_komponen(): void
    {
        $this->bootTenant();

        Setting::updateOrCreate(['key' => 'custom_fields.santri'], ['value' => ['fields' => [
            ['key' => 'golongan_darah', 'label' => 'Golongan Darah', 'type' => 'select', 'options' => ['A', 'B', 'O'], 'required' => true],
            ['key' => 'nama_pondok_lama', 'label' => 'Nama Pondok Sebelumnya', 'type' => 'text', 'options' => [], 'required' => false],
            ['key' => 'tanggal_masuk_lama', 'label' => 'Tgl Masuk Pondok Lama', 'type' => 'date', 'options' => [], 'required' => false],
        ]]]);

        $defs = CustomFieldRegistry::definitions(CustomFieldRegistry::ENTITY_SANTRI);
        $this->assertCount(3, $defs);
        $this->assertTrue(CustomFieldRegistry::hasDefinitions(CustomFieldRegistry::ENTITY_SANTRI));
        $this->assertSame(['A', 'B', 'O'], $defs[0]['options']);
        $this->assertCount(3, CustomFieldRegistry::formComponents(CustomFieldRegistry::ENTITY_SANTRI));
        $this->assertCount(3, CustomFieldRegistry::infolistEntries(CustomFieldRegistry::ENTITY_SANTRI));

        tenancy()->end();
    }

    public function test_nilai_tersimpan_dan_terbaca_kembali_dari_json(): void
    {
        $this->bootTenant();

        $santri = Santri::create([
            'nis' => 'CF-001', 'nama' => 'Santri CF', 'tanggal_lahir' => '2010-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
            'custom_fields' => ['golongan_darah' => 'B', 'hobi' => 'memanah'],
        ]);

        $fresh = Santri::findOrFail($santri->id);
        $this->assertIsArray($fresh->custom_fields);
        $this->assertSame('B', $fresh->custom_fields['golongan_darah']);
        $this->assertSame('memanah', $fresh->custom_fields['hobi']);

        // Persist di DB sebagai JSON (bukan objek Eloquent).
        $rawJson = DB::connection('tenant')->table('santri')->where('id', $santri->id)->value('custom_fields');
        $this->assertSame('B', json_decode((string) $rawJson, true)['golongan_darah']);

        tenancy()->end();
    }

    public function test_tenant_tanpa_definisi_seksi_kosong_tak_error(): void
    {
        $this->bootTenant();

        $this->assertFalse(CustomFieldRegistry::hasDefinitions(CustomFieldRegistry::ENTITY_SANTRI));
        $this->assertSame([], CustomFieldRegistry::definitions(CustomFieldRegistry::ENTITY_SANTRI));
        $this->assertSame([], CustomFieldRegistry::formComponents(CustomFieldRegistry::ENTITY_SANTRI));

        tenancy()->end();
    }

    public function test_definisi_rusak_dan_key_tak_dikenal_diabaikan(): void
    {
        $this->bootTenant();

        // Baris tak valid (tanpa label / tipe asing / duplikat key) harus disaring.
        Setting::updateOrCreate(['key' => 'custom_fields.santri'], ['value' => ['fields' => [
            ['key' => 'valid_satu', 'label' => 'Valid Satu', 'type' => 'text'],
            ['key' => 'tanpa_label', 'label' => '', 'type' => 'text'],
            ['key' => 'valid_satu', 'label' => 'Duplikat', 'type' => 'text'],
            ['key' => 'tipe_asing', 'label' => 'Tipe Asing', 'type' => 'rich_editor'],
            'bukan_array',
        ]]]);

        $defs = CustomFieldRegistry::definitions(CustomFieldRegistry::ENTITY_SANTRI);
        $this->assertCount(1, $defs, 'Hanya 1 baris valid yang bertahan.');
        $this->assertSame('valid_satu', $defs[0]['key']);

        // Nilai untuk key yang tak lagi terdefinisi tetap tersimpan di JSON tapi
        // tak dirender (formComponents hanya untuk key terdefinisi) → tak error.
        $santri = Santri::create([
            'nis' => 'CF-002', 'nama' => 'Santri Legacy', 'tanggal_lahir' => '2010-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
            'custom_fields' => ['valid_satu' => 'ada', 'field_dihapus' => 'nilai lama'],
        ]);
        $this->assertSame('nilai lama', Santri::findOrFail($santri->id)->custom_fields['field_dihapus']);
        $this->assertCount(1, CustomFieldRegistry::formComponents(CustomFieldRegistry::ENTITY_SANTRI));

        tenancy()->end();
    }
}
