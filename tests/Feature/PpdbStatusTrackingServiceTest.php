<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\CalonSantriDokumen;
use Modules\PPDB\Models\HasilUjian;
use Modules\PPDB\Services\PpdbStatusTrackingService;
use RuntimeException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PpdbStatusTrackingServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncatePpdbTables([
            'hasil_ujian',
            'calon_santri_dokumen',
            'calon_santri',
            'domains',
            'tenants',
        ]);

        $this->tenantA = $this->createTestTenant('ppdb-track-a', 'Pesantren A', 'ppdb-track-a');
        $this->tenantB = $this->createTestTenant('ppdb-track-b', 'Pesantren B', 'ppdb-track-b');
    }

    public function test_issue_tracking_link_generates_token_and_url_once(): void
    {
        tenancy()->initialize($this->tenantA);

        $calon = $this->makeCandidate('PPDB-2026-001', 'Ahmad');

        $result = app(PpdbStatusTrackingService::class)->issueTrackingLink($calon);

        $this->assertNotEmpty($result['tracking_token']);
        $this->assertSame('/ppdb/tracking/'.$result['tracking_token'], $result['tracking_path']);
        $this->assertStringContainsString('/ppdb/tracking/', $result['tracking_url']);
        $this->assertSame($result['tracking_token'], $calon->fresh()->tracking_token);

        $second = app(PpdbStatusTrackingService::class)->issueTrackingLink($calon->fresh());
        $this->assertSame($result['tracking_token'], $second['tracking_token']);

        tenancy()->end();
    }

    public function test_resolve_candidate_returns_matching_candidate_inside_same_tenant(): void
    {
        tenancy()->initialize($this->tenantA);

        $calon = $this->makeCandidate('PPDB-2026-002', 'Fatimah');
        $token = app(PpdbStatusTrackingService::class)->issueTrackingLink($calon)['tracking_token'];

        $resolved = app(PpdbStatusTrackingService::class)->resolveCandidate($token);

        $this->assertSame($calon->id, $resolved->id);

        tenancy()->end();
    }

    public function test_get_status_snapshot_returns_progress_counts(): void
    {
        tenancy()->initialize($this->tenantA);

        $calon = $this->makeCandidate('PPDB-2026-003', 'Maryam', 'verifikasi_berkas');
        $token = app(PpdbStatusTrackingService::class)->issueTrackingLink($calon)['tracking_token'];

        CalonSantriDokumen::create([
            'calon_santri_id' => $calon->id,
            'jenis_dokumen' => 'ijazah',
            'file_url' => 'ppdb-documents/1/ijazah/ijazah.pdf',
        ]);

        HasilUjian::create([
            'calon_santri_id' => $calon->id,
            'kategori_ujian' => 'wawancara',
            'nilai' => 88.5,
        ]);

        $snapshot = app(PpdbStatusTrackingService::class)->getStatusSnapshot($token);

        $this->assertSame('PPDB-2026-003', $snapshot['nomor_pendaftaran']);
        $this->assertSame('verifikasi_berkas', $snapshot['status_pendaftaran']);
        $this->assertSame(1, $snapshot['dokumen_terkirim']);
        $this->assertSame(1, $snapshot['hasil_ujian_tercatat']);

        tenancy()->end();
    }

    public function test_tracking_token_is_isolated_per_tenant(): void
    {
        tenancy()->initialize($this->tenantA);

        $calon = $this->makeCandidate('PPDB-2026-004', 'Ali');
        $token = app(PpdbStatusTrackingService::class)->issueTrackingLink($calon)['tracking_token'];

        tenancy()->end();
        tenancy()->initialize($this->tenantB);

        $this->expectException(ModelNotFoundException::class);
        app(PpdbStatusTrackingService::class)->resolveCandidate($token);

        tenancy()->end();
    }

    public function test_tracking_requires_active_tenant_context(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant context belum aktif');

        app(PpdbStatusTrackingService::class)->getStatusSnapshot('dummy-token');
    }

    private function makeCandidate(string $nomor, string $nama, string $status = 'baru'): CalonSantri
    {
        return CalonSantri::create([
            'nomor_pendaftaran' => $nomor,
            'nama' => $nama,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status_pendaftaran' => $status,
        ]);
    }
}
