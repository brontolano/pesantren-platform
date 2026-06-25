<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Services\PpdbAdmissionDecisionService;
use Modules\PPDB\Services\PpdbExamResultService;
use Modules\PPDB\Services\PpdbReregistrationService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F3 end-to-end: pending → input hasil ujian → keputusan lulus → daftar ulang
 * → Santri aktif, dengan KUNCI hasil & status (hasil terkunci pasca-keputusan;
 * keputusan terkunci pasca-daftar-ulang).
 */
class PpdbAlurF3Test extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'calon_santri', 'hasil_ujian', 'santri',
        ]);
        $this->truncatePpdbTables(['hasil_ujian', 'santri', 'calon_santri', 'domains', 'tenants']);
    }

    private function boot(string $prefix): CalonSantri
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        return CalonSantri::create([
            'nomor_pendaftaran' => 'F3-'.strtoupper($prefix), 'nama' => 'Calon F3',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L',
            'status_pendaftaran' => 'baru',
        ]);
    }

    public function test_alur_penuh_pending_sampai_santri_dengan_kunci(): void
    {
        $calon = $this->boot('f3a');
        $decide = app(PpdbAdmissionDecisionService::class);
        $exam = app(PpdbExamResultService::class);
        $rereg = app(PpdbReregistrationService::class);

        // 1) Lulus TANPA hasil ujian → ditolak (prasyarat keputusan).
        try {
            $decide->decide($calon, ['status_pendaftaran' => 'lulus']);
            $this->fail('Keputusan tanpa hasil ujian harus ditolak.');
        } catch (InvalidArgumentException) {
        }

        // 2) Input hasil → keputusan lulus.
        $exam->inputResult($calon, ['kategori_ujian' => 'Tulis', 'nilai' => 85]);
        $calon = $decide->decide($calon->fresh(), ['status_pendaftaran' => 'lulus']);
        $this->assertSame('lulus', $calon->status_pendaftaran);
        $this->assertNotNull($calon->tanggal_keputusan);

        // 3) KUNCI HASIL: nilai tak boleh diubah setelah keputusan terbit.
        try {
            $exam->inputResult($calon, ['kategori_ujian' => 'Tulis', 'nilai' => 10]);
            $this->fail('Hasil ujian pasca-keputusan harus terkunci.');
        } catch (InvalidArgumentException) {
        }

        // 4) Daftar ulang → Santri aktif tercipta & tertaut.
        $calon = $rereg->register($calon->fresh(), ['tahun_angkatan' => '2026/2027']);
        $this->assertSame('registrasi_ulang', $calon->status_pendaftaran);
        $this->assertNotNull($calon->tanggal_registrasi_ulang);
        $this->assertNotNull($calon->santri_id);
        $this->assertSame('aktif', Santri::findOrFail($calon->santri_id)->status);

        // 5) KUNCI STATUS: keputusan tak boleh diubah setelah daftar ulang.
        try {
            $decide->decide($calon->fresh(), ['status_pendaftaran' => 'ditolak']);
            $this->fail('Keputusan pasca-daftar-ulang harus terkunci.');
        } catch (InvalidArgumentException) {
        }
        $this->assertSame('registrasi_ulang', $calon->fresh()->status_pendaftaran);
        $this->assertSame(1, Santri::count(), 'Santri tetap satu & utuh.');
        tenancy()->end();
    }

    public function test_ditolak_tidak_bisa_daftar_ulang(): void
    {
        $calon = $this->boot('f3b');
        app(PpdbExamResultService::class)->inputResult($calon, ['kategori_ujian' => 'Tulis', 'nilai' => 40]);
        app(PpdbAdmissionDecisionService::class)->decide($calon->fresh(), ['status_pendaftaran' => 'ditolak']);

        $this->expectException(\Throwable::class);
        try {
            app(PpdbReregistrationService::class)->register($calon->fresh(), ['tahun_angkatan' => '2026/2027']);
        } finally {
            $this->assertSame(0, Santri::count(), 'Calon ditolak tak boleh jadi Santri.');
            tenancy()->end();
        }
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
