<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\PenempatanKelas;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-05 — Observer sinkron PenempatanKelas → Santri.kelas_id
 * + command kesantrian:audit-penempatan.
 */
class PenempatanKelasSyncTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['penempatan_kelas', 'santri', 'kelas', 'presensi_santri']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function buatTenantDenganSantri(): array
    {
        $tenant = $this->createRuntimeTenant('wp05', 'Pesantren WP05');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        $kelasA = Kelas::create(['tenant_id' => $tenant->id, 'nama_kelas' => 'VII-A', 'tingkat' => '7', 'capacity' => 35]);
        $kelasB = Kelas::create(['tenant_id' => $tenant->id, 'nama_kelas' => 'VII-B', 'tingkat' => '7', 'capacity' => 35]);

        $santri = Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => 'WP05-001',
            'nama' => 'Santri WP05',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);

        return [$tenant, $santri, $kelasA, $kelasB];
    }

    public function test_penempatan_baru_is_current_menyinkronkan_kelas_dan_menonaktifkan_lama(): void
    {
        [$tenant, $santri, $kelasA, $kelasB] = $this->buatTenantDenganSantri();

        $lama = PenempatanKelas::create([
            'tenant_id' => $tenant->id,
            'santri_id' => $santri->id,
            'kelas_id' => $kelasA->id,
            'tahun_akademik' => '2026/2027',
            'tanggal_penempatan' => '2026-01-10',
            'is_current' => true,
        ]);

        $this->assertEquals($kelasA->id, $santri->fresh()->kelas_id, 'Observer harus mengisi kelas_id pada penempatan pertama');

        $baru = PenempatanKelas::create([
            'tenant_id' => $tenant->id,
            'santri_id' => $santri->id,
            'kelas_id' => $kelasB->id,
            'tahun_akademik' => '2026/2027',
            'tanggal_penempatan' => '2026-06-01',
            'alasan_mutasi' => 'Mutasi WP05',
            'is_current' => true,
        ]);

        $this->assertEquals($kelasB->id, $santri->fresh()->kelas_id);
        $this->assertFalse($lama->fresh()->is_current, 'Penempatan lama harus otomatis nonaktif');
        $this->assertTrue($baru->fresh()->is_current);
        $this->assertEquals(
            1,
            PenempatanKelas::where('santri_id', $santri->id)->where('is_current', true)->count(),
        );
    }

    public function test_presensi_baru_tercatat_ke_kelas_baru_setelah_mutasi(): void
    {
        [$tenant, $santri, $kelasA, $kelasB] = $this->buatTenantDenganSantri();

        PenempatanKelas::create([
            'tenant_id' => $tenant->id, 'santri_id' => $santri->id, 'kelas_id' => $kelasA->id,
            'tahun_akademik' => '2026/2027', 'tanggal_penempatan' => '2026-01-10', 'is_current' => true,
        ]);
        PenempatanKelas::create([
            'tenant_id' => $tenant->id, 'santri_id' => $santri->id, 'kelas_id' => $kelasB->id,
            'tahun_akademik' => '2026/2027', 'tanggal_penempatan' => '2026-06-01', 'is_current' => true,
        ]);

        // Pola jalur tulis hilir: presensi memakai kelas_id terkini milik santri.
        $presensi = PresensiSantri::create([
            'tenant_id' => $tenant->id,
            'santri_id' => $santri->id,
            'kelas_id' => $santri->fresh()->kelas_id,
            'tanggal' => '2026-06-02',
            'status' => 'hadir',
        ]);

        $this->assertEquals($kelasB->id, $presensi->fresh()->kelas_id, 'Presensi baru harus tercatat ke kelas baru');
    }

    public function test_update_penempatan_lama_jadi_current_lagi_sinkron_balik(): void
    {
        [$tenant, $santri, $kelasA, $kelasB] = $this->buatTenantDenganSantri();

        $lama = PenempatanKelas::create([
            'tenant_id' => $tenant->id, 'santri_id' => $santri->id, 'kelas_id' => $kelasA->id,
            'tahun_akademik' => '2026/2027', 'tanggal_penempatan' => '2026-01-10', 'is_current' => true,
        ]);
        $baru = PenempatanKelas::create([
            'tenant_id' => $tenant->id, 'santri_id' => $santri->id, 'kelas_id' => $kelasB->id,
            'tahun_akademik' => '2026/2027', 'tanggal_penempatan' => '2026-06-01', 'is_current' => true,
        ]);

        $lama->fresh()->update(['is_current' => true]);

        $this->assertEquals($kelasA->id, $santri->fresh()->kelas_id, 'kelas_id harus sinkron balik ke kelas A');
        $this->assertFalse($baru->fresh()->is_current);
        $this->assertEquals(
            1,
            PenempatanKelas::where('santri_id', $santri->id)->where('is_current', true)->count(),
        );
    }

    public function test_hapus_penempatan_aktif_menjatuhkan_kelas_ke_pengganti_atau_null(): void
    {
        [$tenant, $santri, $kelasA, $kelasB] = $this->buatTenantDenganSantri();

        PenempatanKelas::create([
            'tenant_id' => $tenant->id, 'santri_id' => $santri->id, 'kelas_id' => $kelasA->id,
            'tahun_akademik' => '2026/2027', 'tanggal_penempatan' => '2026-01-10', 'is_current' => true,
        ]);
        $aktif = PenempatanKelas::create([
            'tenant_id' => $tenant->id, 'santri_id' => $santri->id, 'kelas_id' => $kelasB->id,
            'tahun_akademik' => '2026/2027', 'tanggal_penempatan' => '2026-06-01', 'is_current' => true,
        ]);

        $aktif->delete();

        // Tidak ada penempatan aktif lain (yang lama sudah false) → kelas_id NULL.
        $this->assertNull($santri->fresh()->kelas_id, 'Tanpa penempatan aktif pengganti, kelas_id harus NULL');
    }

    public function test_nonaktifkan_manual_penempatan_aktif_satusatunya_menullkan_kelas(): void
    {
        // Skenario EditAction Filament: toggle is_current → false pada
        // satu-satunya penempatan aktif. Harus konsisten dengan delete.
        [$tenant, $santri, $kelasA, $kelasB] = $this->buatTenantDenganSantri();

        $aktif = PenempatanKelas::create([
            'tenant_id' => $tenant->id, 'santri_id' => $santri->id, 'kelas_id' => $kelasA->id,
            'tahun_akademik' => '2026/2027', 'tanggal_penempatan' => '2026-01-10', 'is_current' => true,
        ]);

        $aktif->update(['is_current' => false]);

        $this->assertNull($santri->fresh()->kelas_id, 'Nonaktif manual tanpa pengganti harus NULL-kan kelas_id (konsisten dgn delete)');
    }

    public function test_nonaktifkan_manual_jatuh_ke_penempatan_aktif_pengganti(): void
    {
        [$tenant, $santri, $kelasA, $kelasB] = $this->buatTenantDenganSantri();

        $lama = PenempatanKelas::create([
            'tenant_id' => $tenant->id, 'santri_id' => $santri->id, 'kelas_id' => $kelasA->id,
            'tahun_akademik' => '2026/2027', 'tanggal_penempatan' => '2026-01-10', 'is_current' => true,
        ]);
        $baru = PenempatanKelas::create([
            'tenant_id' => $tenant->id, 'santri_id' => $santri->id, 'kelas_id' => $kelasB->id,
            'tahun_akademik' => '2026/2027', 'tanggal_penempatan' => '2026-06-01', 'is_current' => true,
        ]);

        // Dua current dilarang index, rute realistis: nonaktifkan yang baru
        // (→ NULL), lalu aktifkan kembali yang lama (→ kelas A).
        $baru->update(['is_current' => false]);
        $lama->fresh()->update(['is_current' => true]);

        $this->assertEquals($kelasA->id, $santri->fresh()->kelas_id);
    }

    public function test_partial_unique_index_menolak_insert_dobel_current_yang_lolos_observer(): void
    {
        // Bulk insert via query builder MELEWATI observer (risiko c) —
        // index parsial harus jadi pagar terakhir di level DB.
        [$tenant, $santri, $kelasA, $kelasB] = $this->buatTenantDenganSantri();

        PenempatanKelas::create([
            'tenant_id' => $tenant->id, 'santri_id' => $santri->id, 'kelas_id' => $kelasA->id,
            'tahun_akademik' => '2026/2027', 'tanggal_penempatan' => '2026-01-10', 'is_current' => true,
        ]);

        try {
            DB::table('penempatan_kelas')->insert([
                'tenant_id' => $tenant->id,
                'santri_id' => $santri->id,
                'kelas_id' => $kelasB->id,
                'tahun_akademik' => '2026/2027',
                'tanggal_penempatan' => '2026-06-01',
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // HARUS unique violation (23505) dari index parsial — bukan
            // error lain yang kebetulan lolos (false positive).
            $this->assertSame('23505', $e->errorInfo[0] ?? null, 'Harus unique_violation dari index parsial: '.$e->getMessage());
            $this->assertStringContainsString('penempatan_kelas_santri_current_unique', $e->getMessage());

            return;
        }

        $this->fail('Insert dobel-current via query builder harus ditolak partial unique index.');
    }

    public function test_command_audit_mendeteksi_drift_dan_fix_memperbaiki(): void
    {
        [$tenant, $santri, $kelasA, $kelasB] = $this->buatTenantDenganSantri();

        PenempatanKelas::create([
            'tenant_id' => $tenant->id, 'santri_id' => $santri->id, 'kelas_id' => $kelasB->id,
            'tahun_akademik' => '2026/2027', 'tanggal_penempatan' => '2026-06-01', 'is_current' => true,
        ]);

        // Buat drift sengaja: tulis langsung tanpa event.
        Santri::query()->whereKey($santri->id)->update(['kelas_id' => $kelasA->id]);

        tenancy()->end();

        // Tanpa --fix: lapor saja, data tidak berubah.
        Artisan::call('kesantrian:audit-penempatan', ['tenant' => $tenant->id]);
        $output = Artisan::output();
        $this->assertStringContainsString('Santri WP05', $output);
        $this->assertStringContainsString('Drift', $output);

        tenancy()->initialize($tenant);
        $this->assertEquals($kelasA->id, $santri->fresh()->kelas_id, 'Audit tanpa --fix TIDAK boleh mengubah data');
        tenancy()->end();

        // Dengan --fix: kelas_id mengikuti penempatan aktif.
        Artisan::call('kesantrian:audit-penempatan', ['tenant' => $tenant->id, '--fix' => true]);

        tenancy()->initialize($tenant);
        $this->assertEquals($kelasB->id, $santri->fresh()->kelas_id, '--fix harus menyetel kelas_id mengikuti penempatan aktif');
        tenancy()->end();

        // Setelah fix: audit bersih.
        Artisan::call('kesantrian:audit-penempatan', ['tenant' => $tenant->id]);
        $this->assertStringContainsString('Sinkron', Artisan::output());
    }

    public function test_observer_tidak_rekursif_dan_jumlah_query_wajar(): void
    {
        [$tenant, $santri, $kelasA, $kelasB] = $this->buatTenantDenganSantri();

        PenempatanKelas::create([
            'tenant_id' => $tenant->id, 'santri_id' => $santri->id, 'kelas_id' => $kelasA->id,
            'tahun_akademik' => '2026/2027', 'tanggal_penempatan' => '2026-01-10', 'is_current' => true,
        ]);

        $connection = PenempatanKelas::query()->getModel()->getConnection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        PenempatanKelas::create([
            'tenant_id' => $tenant->id, 'santri_id' => $santri->id, 'kelas_id' => $kelasB->id,
            'tahun_akademik' => '2026/2027', 'tanggal_penempatan' => '2026-06-01', 'is_current' => true,
        ]);

        $jumlahQuery = count($connection->getQueryLog());
        $connection->disableQueryLog();

        // 1 insert + 1 update penempatan lain + 1 update santri (toleransi kecil).
        $this->assertLessThanOrEqual(5, $jumlahQuery, 'Observer meledakkan query / terindikasi rekursi');

        // Update kolom non-relevan tidak boleh memicu update sinkronisasi.
        $aktif = PenempatanKelas::where('santri_id', $santri->id)->where('is_current', true)->first();
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        $aktif->update(['alasan_mutasi' => 'catatan saja']);
        $jumlahQueryUpdate = count($connection->getQueryLog());
        $connection->disableQueryLog();

        $this->assertLessThanOrEqual(2, $jumlahQueryUpdate, 'Update kolom non-relevan harus bebas query sinkronisasi');
        $this->assertEquals($kelasB->id, $santri->fresh()->kelas_id);
    }
}
