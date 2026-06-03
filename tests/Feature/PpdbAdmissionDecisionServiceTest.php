<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\HasilUjian;
use Modules\PPDB\Services\PpdbAdmissionDecisionService;
use RuntimeException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PpdbAdmissionDecisionServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetPpdbSchema();

        $this->tenantA = Tenant::create([
            'id' => 'ppdb-keputusan-a',
            'name' => 'Pesantren A',
            'is_active' => true,
        ]);
        $this->tenantA->domains()->create(['domain' => 'ppdb-keputusan-a']);

        $this->tenantB = Tenant::create([
            'id' => 'ppdb-keputusan-b',
            'name' => 'Pesantren B',
            'is_active' => true,
        ]);
        $this->tenantB->domains()->create(['domain' => 'ppdb-keputusan-b']);
    }

    public function test_decide_updates_candidate_to_lulus_with_note_and_timestamp(): void
    {
        tenancy()->initialize($this->tenantA);

        $candidate = $this->makeCandidate('PPDB-2026-101', 'Abdullah');
        $this->makeExamResult($candidate, 'wawancara', 91.5);
        $decisionAt = Carbon::parse('2026-06-03 22:59:00');

        $updated = app(PpdbAdmissionDecisionService::class)->decide($candidate, [
            'status_pendaftaran' => 'Lulus',
            'catatan_keputusan' => 'Lulus tahap seleksi utama.',
            'tanggal_keputusan' => $decisionAt,
        ]);

        $this->assertSame('lulus', $updated->status_pendaftaran);
        $this->assertSame('Lulus tahap seleksi utama.', $updated->catatan_keputusan);
        $this->assertTrue($updated->tanggal_keputusan->equalTo($decisionAt));

        tenancy()->end();
    }

    public function test_decide_allows_ditolak_without_exam_result(): void
    {
        tenancy()->initialize($this->tenantA);

        $candidate = $this->makeCandidate('PPDB-2026-102', 'Aisyah');

        $updated = app(PpdbAdmissionDecisionService::class)->decide($candidate, [
            'status_pendaftaran' => 'ditolak',
            'catatan_keputusan' => 'Dokumen belum memenuhi syarat.',
        ]);

        $this->assertSame('ditolak', $updated->status_pendaftaran);
        $this->assertSame('Dokumen belum memenuhi syarat.', $updated->catatan_keputusan);
        $this->assertNotNull($updated->tanggal_keputusan);

        tenancy()->end();
    }

    public function test_decide_requires_exam_result_for_lulus_or_cadangan(): void
    {
        tenancy()->initialize($this->tenantA);

        $candidate = $this->makeCandidate('PPDB-2026-103', 'Hasan');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('membutuhkan minimal satu hasil ujian');

        app(PpdbAdmissionDecisionService::class)->decide($candidate, [
            'status_pendaftaran' => 'cadangan',
        ]);

        tenancy()->end();
    }

    public function test_decide_is_isolated_per_tenant(): void
    {
        tenancy()->initialize($this->tenantA);
        $candidate = $this->makeCandidate('PPDB-2026-104', 'Zainab');
        $this->makeExamResult($candidate, 'tes_tulis', 88);
        tenancy()->end();

        tenancy()->initialize($this->tenantB);

        $this->expectException(ModelNotFoundException::class);

        app(PpdbAdmissionDecisionService::class)->decide(1, [
            'status_pendaftaran' => 'ditolak',
        ]);

        tenancy()->end();
    }

    public function test_decide_rejects_unknown_status(): void
    {
        tenancy()->initialize($this->tenantA);

        $candidate = $this->makeCandidate('PPDB-2026-105', 'Khadijah');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('harus salah satu dari');

        app(PpdbAdmissionDecisionService::class)->decide($candidate, [
            'status_pendaftaran' => 'registrasi_ulang',
        ]);

        tenancy()->end();
    }

    public function test_decide_requires_active_tenant_context(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant context belum aktif');

        app(PpdbAdmissionDecisionService::class)->decide(1, [
            'status_pendaftaran' => 'ditolak',
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

    private function makeExamResult(CalonSantri $candidate, string $kategori, float $nilai): HasilUjian
    {
        return HasilUjian::create([
            'calon_santri_id' => $candidate->id,
            'kategori_ujian' => $kategori,
            'nilai' => $nilai,
        ]);
    }
}
