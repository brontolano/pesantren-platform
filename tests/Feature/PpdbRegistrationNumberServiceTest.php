<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Services\PpdbRegistrationNumberService;
use RuntimeException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PpdbRegistrationNumberServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncatePpdbTables([
            'calon_santri',
            'domains',
            'tenants',
        ]);

        $this->tenantA = $this->createTestTenant('ppdb-a', 'Pesantren A', 'ppdb-a');
        $this->tenantB = $this->createTestTenant('ppdb-b', 'Pesantren B', 'ppdb-b');
    }

    public function test_generate_returns_first_number_when_tenant_has_no_candidate(): void
    {
        tenancy()->initialize($this->tenantA);

        $number = app(PpdbRegistrationNumberService::class)->generate(2026);

        $this->assertSame('PPDB-2026-001', $number);

        tenancy()->end();
    }

    public function test_generate_increments_sequence_within_same_tenant_and_year(): void
    {
        tenancy()->initialize($this->tenantA);

        CalonSantri::create([
            'nomor_pendaftaran' => 'PPDB-2026-001',
            'nama' => 'Ahmad',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status_pendaftaran' => 'baru',
        ]);

        CalonSantri::create([
            'nomor_pendaftaran' => 'PPDB-2026-002',
            'nama' => 'Fatimah',
            'tanggal_lahir' => '2012-02-01',
            'jenis_kelamin' => 'P',
            'status_pendaftaran' => 'baru',
        ]);

        $number = app(PpdbRegistrationNumberService::class)->generate(2026);

        $this->assertSame('PPDB-2026-003', $number);

        tenancy()->end();
    }

    public function test_generate_resets_sequence_for_new_year(): void
    {
        tenancy()->initialize($this->tenantA);

        CalonSantri::create([
            'nomor_pendaftaran' => 'PPDB-2026-009',
            'nama' => 'Yusuf',
            'tanggal_lahir' => '2011-03-03',
            'jenis_kelamin' => 'L',
            'status_pendaftaran' => 'baru',
        ]);

        $number = app(PpdbRegistrationNumberService::class)->generate(2027);

        $this->assertSame('PPDB-2027-001', $number);

        tenancy()->end();
    }

    public function test_generate_is_isolated_per_tenant(): void
    {
        tenancy()->initialize($this->tenantA);

        CalonSantri::create([
            'nomor_pendaftaran' => 'PPDB-2026-015',
            'nama' => 'Ali',
            'tanggal_lahir' => '2011-04-04',
            'jenis_kelamin' => 'L',
            'status_pendaftaran' => 'baru',
        ]);

        tenancy()->end();

        tenancy()->initialize($this->tenantB);

        $number = app(PpdbRegistrationNumberService::class)->generate(2026);

        $this->assertSame('PPDB-2026-001', $number);

        tenancy()->end();
    }

    public function test_generate_requires_active_tenant_context(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant context belum aktif');

        app(PpdbRegistrationNumberService::class)->generate(2026);
    }
}
