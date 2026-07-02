<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Services\PpdbExamResultService;
use RuntimeException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PpdbExamResultServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncatePpdbTables([
            'hasil_ujian',
            'calon_santri',
            'domains',
            'tenants',
        ]);

        $this->tenantA = $this->createTestTenant('ppdb-ujian-a', 'Pesantren A', 'ppdb-ujian-a');
        $this->tenantB = $this->createTestTenant('ppdb-ujian-b', 'Pesantren B', 'ppdb-ujian-b');
    }

    public function test_input_result_creates_exam_result_for_candidate(): void
    {
        tenancy()->initialize($this->tenantA);

        $candidate = $this->makeCandidate('PPDB-2026-001', 'Ahmad');

        $result = app(PpdbExamResultService::class)->inputResult($candidate, [
            'kategori_ujian' => 'Wawancara',
            'nilai' => 87.5,
            'catatan' => 'Komunikasi baik.',
        ]);

        $this->assertSame($candidate->id, $result->calon_santri_id);
        $this->assertSame('wawancara', $result->kategori_ujian);
        $this->assertSame('87.50', (string) $result->nilai);
        $this->assertSame('Komunikasi baik.', $result->catatan);

        tenancy()->end();
    }

    public function test_input_result_updates_existing_category_for_same_candidate(): void
    {
        tenancy()->initialize($this->tenantA);

        $candidate = $this->makeCandidate('PPDB-2026-002', 'Fatimah');

        $first = app(PpdbExamResultService::class)->inputResult($candidate, [
            'kategori_ujian' => 'Tes Tulis',
            'nilai' => 70,
        ]);

        $updated = app(PpdbExamResultService::class)->inputResult($candidate, [
            'kategori_ujian' => 'Tes Tulis',
            'nilai' => 92.25,
            'catatan' => 'Nilai diperbarui oleh TU.',
        ]);

        $this->assertSame($first->id, $updated->id);
        $this->assertSame('92.25', (string) $updated->nilai);
        $this->assertSame('Nilai diperbarui oleh TU.', $updated->catatan);
        $this->assertEquals(1, $candidate->hasilUjian()->count());

        tenancy()->end();
    }

    public function test_input_result_is_isolated_per_tenant(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->makeCandidate('PPDB-2026-003', 'Ali');
        tenancy()->end();

        tenancy()->initialize($this->tenantB);

        $this->expectException(ModelNotFoundException::class);

        app(PpdbExamResultService::class)->inputResult(1, [
            'kategori_ujian' => 'wawancara',
            'nilai' => 80,
        ]);

        tenancy()->end();
    }

    public function test_input_result_rejects_invalid_score_range(): void
    {
        tenancy()->initialize($this->tenantA);

        $candidate = $this->makeCandidate('PPDB-2026-004', 'Maryam');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('rentang 0 sampai 100');

        app(PpdbExamResultService::class)->inputResult($candidate, [
            'kategori_ujian' => 'Wawancara',
            'nilai' => 120,
        ]);

        tenancy()->end();
    }

    public function test_input_result_requires_active_tenant_context(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant context belum aktif');

        app(PpdbExamResultService::class)->inputResult(1, [
            'kategori_ujian' => 'wawancara',
            'nilai' => 85,
        ]);
    }

    private function makeCandidate(string $nomor, string $nama): CalonSantri
    {
        return CalonSantri::create([
            'nomor_pendaftaran' => $nomor,
            'nama' => $nama,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status_pendaftaran' => 'baru',
        ]);
    }
}
