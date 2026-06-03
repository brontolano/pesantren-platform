<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tagihan;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Services\PpdbBillingService;
use RuntimeException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PpdbBillingServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetPpdbSchema();

        $this->tenantA = Tenant::create([
            'id' => 'ppdb-tagihan-a',
            'name' => 'Pesantren A',
            'is_active' => true,
        ]);
        $this->tenantA->domains()->create(['domain' => 'ppdb-tagihan-a']);

        $this->tenantB = Tenant::create([
            'id' => 'ppdb-tagihan-b',
            'name' => 'Pesantren B',
            'is_active' => true,
        ]);
        $this->tenantB->domains()->create(['domain' => 'ppdb-tagihan-b']);
    }

    public function test_create_or_sync_tagihan_creates_ppdb_and_keuangan_records(): void
    {
        tenancy()->initialize($this->tenantA);

        $candidate = $this->makeRegisteredCandidate('PPDB-2026-301', 'Abdullah');

        $invoice = app(PpdbBillingService::class)->createOrSyncTagihan($candidate, [
            'tipe' => 'registrasi_ulang',
            'nominal' => 1500000,
            'diskon' => 100000,
            'jatuh_tempo' => '2026-07-10',
            'periode_akademik' => '2026/2027',
            'keterangan' => 'Biaya registrasi ulang gelombang 1',
        ]);

        $this->assertSame('ppdb-tagihan-a', $invoice->tenant_id);
        $this->assertSame($candidate->id, $invoice->calon_santri_id);
        $this->assertNotNull($invoice->keuangan_tagihan_id);
        $this->assertSame('belum_bayar', $invoice->status);
        $this->assertEquals(1400000.0, (float) $invoice->nominal);
        $this->assertSame('registrasi_ulang', $invoice->keuanganTagihan->jenis_tagihan);
        $this->assertSame($candidate->santri_id, $invoice->keuanganTagihan->santri_id);
        $this->assertEquals(1400000.0, (float) $invoice->keuanganTagihan->nominal_akhir);
        $this->assertDatabaseCount('tagihan_ppdb', 1);
        $this->assertDatabaseCount('tagihan', 1);

        tenancy()->end();
    }

    public function test_create_or_sync_tagihan_is_idempotent_and_updates_existing_invoice(): void
    {
        tenancy()->initialize($this->tenantA);

        $candidate = $this->makeRegisteredCandidate('PPDB-2026-302', 'Aisyah', 'P');

        $first = app(PpdbBillingService::class)->createOrSyncTagihan($candidate, [
            'tipe' => 'registrasi_ulang',
            'nominal' => 1250000,
            'jatuh_tempo' => '2026-07-11',
            'periode_akademik' => '2026/2027',
        ]);

        $second = app(PpdbBillingService::class)->createOrSyncTagihan($candidate->fresh(), [
            'tipe' => 'registrasi_ulang',
            'nominal' => 1250000,
            'diskon' => 250000,
            'jatuh_tempo' => '2026-07-15',
            'periode_akademik' => '2026/2027',
            'status' => 'sebagian',
            'keterangan' => 'Update cicilan tahap awal',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->keuangan_tagihan_id, $second->keuangan_tagihan_id);
        $this->assertDatabaseCount('tagihan_ppdb', 1);
        $this->assertDatabaseCount('tagihan', 1);

        $linked = Tagihan::query()->findOrFail($second->keuangan_tagihan_id);
        $this->assertEquals(1000000.0, (float) $linked->nominal_akhir);
        $this->assertSame('sebagian', $linked->status);
        $this->assertSame('Update cicilan tahap awal', $linked->keterangan);

        tenancy()->end();
    }

    public function test_create_or_sync_tagihan_requires_candidate_with_santri_link(): void
    {
        tenancy()->initialize($this->tenantA);

        $candidate = CalonSantri::create([
            'nomor_pendaftaran' => 'PPDB-2026-303',
            'nama' => 'Hasan',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status_pendaftaran' => 'lulus',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sudah dikonversi menjadi santri');

        app(PpdbBillingService::class)->createOrSyncTagihan($candidate, [
            'tipe' => 'registrasi_ulang',
            'nominal' => 900000,
            'jatuh_tempo' => '2026-07-20',
        ]);

        tenancy()->end();
    }

    public function test_create_or_sync_tagihan_is_isolated_per_tenant(): void
    {
        tenancy()->initialize($this->tenantA);
        $candidate = $this->makeRegisteredCandidate('PPDB-2026-304', 'Maryam', 'P');
        tenancy()->end();

        tenancy()->initialize($this->tenantB);

        $this->expectException(ModelNotFoundException::class);

        app(PpdbBillingService::class)->createOrSyncTagihan($candidate->id, [
            'tipe' => 'registrasi_ulang',
            'nominal' => 1100000,
            'jatuh_tempo' => '2026-07-22',
        ]);

        tenancy()->end();
    }

    public function test_create_or_sync_tagihan_requires_active_tenant_context(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant context belum aktif');

        app(PpdbBillingService::class)->createOrSyncTagihan(1, [
            'tipe' => 'registrasi_ulang',
            'nominal' => 1000000,
            'jatuh_tempo' => '2026-07-21',
        ]);
    }

    private function makeRegisteredCandidate(string $nomor, string $nama, string $gender = 'L'): CalonSantri
    {
        $santri = Santri::create([
            'tenant_id' => tenancy()->tenant->getTenantKey(),
            'nis' => 'SANTRI-'.str_replace(['PPDB-', '-'], '', $nomor),
            'nama' => $nama,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => $gender,
            'status' => 'aktif',
            'tahun_angkatan' => '2026/2027',
        ]);

        return CalonSantri::create([
            'tenant_id' => tenancy()->tenant->getTenantKey(),
            'santri_id' => $santri->id,
            'nomor_pendaftaran' => $nomor,
            'nama' => $nama,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => $gender,
            'status_pendaftaran' => 'registrasi_ulang',
        ]);
    }
}
