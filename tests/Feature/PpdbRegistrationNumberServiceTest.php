<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Services\PpdbRegistrationNumberService;
use RuntimeException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Nomor pendaftaran PPDB-{tahun}-{urut3}: berurut per tenant per tahun, dan
 * RACE-SAFE — createWithNomor() mengunci + insert dalam satu transaksi dengan
 * retry sekali saat tabrakan nomor (unique violation).
 */
class PpdbRegistrationNumberServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'calon_santri']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    /** Tenant runtime (DB fisik ter-provision via event) + inisialisasi tenancy. */
    private function boot(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        return $tenant;
    }

    private function atributCalon(string $nama): array
    {
        return [
            'nama' => $nama,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status_pendaftaran' => 'baru',
        ];
    }

    public function test_generate_returns_first_number_when_tenant_has_no_candidate(): void
    {
        $this->boot('ppdba');

        $number = app(PpdbRegistrationNumberService::class)->generate(2026);

        $this->assertSame('PPDB-2026-001', $number);

        tenancy()->end();
    }

    public function test_generate_increments_sequence_within_same_tenant_and_year(): void
    {
        $this->boot('ppdba');

        CalonSantri::create($this->atributCalon('Ahmad') + ['nomor_pendaftaran' => 'PPDB-2026-001']);
        CalonSantri::create($this->atributCalon('Fatimah') + ['nomor_pendaftaran' => 'PPDB-2026-002']);

        $number = app(PpdbRegistrationNumberService::class)->generate(2026);

        $this->assertSame('PPDB-2026-003', $number);

        tenancy()->end();
    }

    public function test_generate_resets_sequence_for_new_year(): void
    {
        $this->boot('ppdba');

        CalonSantri::create($this->atributCalon('Yusuf') + ['nomor_pendaftaran' => 'PPDB-2026-009']);

        $number = app(PpdbRegistrationNumberService::class)->generate(2027);

        $this->assertSame('PPDB-2027-001', $number);

        tenancy()->end();
    }

    public function test_generate_is_isolated_per_tenant(): void
    {
        $this->boot('ppdba');
        CalonSantri::create($this->atributCalon('Ali') + ['nomor_pendaftaran' => 'PPDB-2026-015']);
        tenancy()->end();

        $this->boot('ppdbb');

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

    public function test_create_with_nomor_assigns_sequential_unique_numbers(): void
    {
        $this->boot('ppdba');

        $service = app(PpdbRegistrationNumberService::class);

        $satu = $service->createWithNomor($this->atributCalon('Ahmad'), 2026);
        $dua = $service->createWithNomor($this->atributCalon('Fatimah'), 2026);
        $tiga = $service->createWithNomor($this->atributCalon('Yusuf'), 2026);

        $this->assertSame('PPDB-2026-001', $satu->nomor_pendaftaran);
        $this->assertSame('PPDB-2026-002', $dua->nomor_pendaftaran);
        $this->assertSame('PPDB-2026-003', $tiga->nomor_pendaftaran);

        // Format PPDB-{tahun}-{urut3} tidak berubah.
        $this->assertMatchesRegularExpression('/^PPDB-\d{4}-\d{3}$/', $tiga->nomor_pendaftaran);

        tenancy()->end();
    }

    public function test_create_with_nomor_retries_once_on_number_collision(): void
    {
        $this->boot('ppdba');

        // Sudah ada 001 — service tabrakan sengaja mengembalikan nomor basi
        // (001) pada percobaan pertama, mensimulasikan dua pendaftar bersamaan
        // yang membaca nomor terakhir yang sama.
        CalonSantri::create($this->atributCalon('Sudah Ada') + ['nomor_pendaftaran' => 'PPDB-2026-001']);

        $service = new class extends PpdbRegistrationNumberService
        {
            public int $panggilan = 0;

            protected function nomorBerikutnya(string $prefix): string
            {
                $this->panggilan++;

                // Percobaan pertama: nomor basi → unique violation → retry.
                return $this->panggilan === 1 ? $prefix.'001' : parent::nomorBerikutnya($prefix);
            }
        };

        $calon = $service->createWithNomor($this->atributCalon('Pendaftar Kedua'), 2026);

        $this->assertSame(2, $service->panggilan, 'Retry harus terjadi tepat sekali.');
        $this->assertSame('PPDB-2026-002', $calon->nomor_pendaftaran);
        $this->assertSame(2, CalonSantri::query()->count());
        $this->assertSame(
            1,
            CalonSantri::query()->where('nomor_pendaftaran', 'PPDB-2026-001')->count(),
            'Tidak boleh ada nomor ganda.'
        );

        tenancy()->end();
    }

    public function test_create_with_nomor_requires_active_tenant_context(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant context belum aktif');

        app(PpdbRegistrationNumberService::class)->createWithNomor($this->atributCalon('Tanpa Tenant'), 2026);
    }
}
