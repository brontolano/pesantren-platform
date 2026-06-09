<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\HasilUjian;
use Modules\PPDB\Services\PpdbReregistrationService;
use RuntimeException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PpdbReregistrationServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncatePpdbTables([
            'penempatan_asrama',
            'kamar_asrama',
            'blok_asrama',
            'hasil_ujian',
            'santri',
            'calon_santri',
            'domains',
            'tenants',
        ]);

        $this->tenantA = $this->createTestTenant('ppdb-daftar-ulang-a', 'Pesantren A', 'ppdb-daftar-ulang-a');
        $this->tenantB = $this->createTestTenant('ppdb-daftar-ulang-b', 'Pesantren B', 'ppdb-daftar-ulang-b');
    }

    public function test_register_creates_santri_for_accepted_candidate(): void
    {
        tenancy()->initialize($this->tenantA);

        $candidate = $this->makeAcceptedCandidate('PPDB-2026-201', 'Abdullah');

        $updated = app(PpdbReregistrationService::class)->register($candidate, [
            'tahun_angkatan' => '2026/2027',
            'tempat_lahir' => 'Bandung',
            'alamat' => 'Jl. Pesantren No. 1',
            'no_hp_wali' => '08123456789',
            'email_wali' => 'wali.abdullah@example.com',
            'nama_ayah' => 'Bapak Abdullah',
            'nama_ibu' => 'Ibu Abdullah',
        ]);

        $this->assertSame('registrasi_ulang', $updated->status_pendaftaran);
        $this->assertNotNull($updated->tanggal_registrasi_ulang);
        $this->assertNotNull($updated->santri_id);
        $this->assertTrue($updated->relationLoaded('santri'));
        $this->assertSame('Abdullah', $updated->santri->nama);
        $this->assertSame('2026/2027', $updated->santri->tahun_angkatan);
        $this->assertSame('aktif', $updated->santri->status);
        $this->assertSame('Bandung', $updated->santri->tempat_lahir);
        $this->assertSame('wali.abdullah@example.com', $updated->santri->email_wali);

        tenancy()->end();
    }

    public function test_register_auto_generates_nis_when_missing(): void
    {
        tenancy()->initialize($this->tenantA);

        Santri::create([
            'tenant_id' => $this->tenantA->getKey(),
            'nis' => 'S20260009',
            'nama' => 'Santri Lama',
            'tanggal_lahir' => '2011-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);

        $candidate = $this->makeAcceptedCandidate('PPDB-2026-202', 'Fatimah', 'P');

        $updated = app(PpdbReregistrationService::class)->register($candidate, [
            'tahun_angkatan' => '2026/2027',
        ]);

        $this->assertSame('S20260010', $updated->santri->nis);

        tenancy()->end();
    }

    public function test_register_is_idempotent_and_updates_existing_santri(): void
    {
        tenancy()->initialize($this->tenantA);

        $candidate = $this->makeAcceptedCandidate('PPDB-2026-203', 'Hasan');

        $first = app(PpdbReregistrationService::class)->register($candidate, [
            'tahun_angkatan' => '2026/2027',
            'nis' => 'REG001',
            'alamat' => 'Alamat Lama',
        ]);

        $second = app(PpdbReregistrationService::class)->register($candidate->fresh(), [
            'tahun_angkatan' => '2026/2027',
            'nis' => 'REG001',
            'alamat' => 'Alamat Baru',
        ]);

        $this->assertSame($first->santri_id, $second->santri_id);
        $this->assertSame(1, Santri::query()->count());
        $this->assertSame('Alamat Baru', $second->santri->alamat);

        tenancy()->end();
    }

    public function test_register_rejects_candidate_without_accepted_status(): void
    {
        tenancy()->initialize($this->tenantA);

        $candidate = CalonSantri::create([
            'nomor_pendaftaran' => 'PPDB-2026-204',
            'nama' => 'Khadijah',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'P',
            'status_pendaftaran' => 'baru',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Registrasi ulang hanya boleh');

        app(PpdbReregistrationService::class)->register($candidate, [
            'tahun_angkatan' => '2026/2027',
        ]);

        tenancy()->end();
    }

    public function test_register_is_isolated_per_tenant(): void
    {
        tenancy()->initialize($this->tenantA);
        $candidate = $this->makeAcceptedCandidate('PPDB-2026-205', 'Maryam', 'P');
        tenancy()->end();

        tenancy()->initialize($this->tenantB);

        $this->expectException(ModelNotFoundException::class);

        app(PpdbReregistrationService::class)->register($candidate->id, [
            'tahun_angkatan' => '2026/2027',
        ]);

        tenancy()->end();
    }

    public function test_register_requires_active_tenant_context(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant context belum aktif');

        app(PpdbReregistrationService::class)->register(1, [
            'tahun_angkatan' => '2026/2027',
        ]);
    }

    public function test_register_menempatkan_santri_ke_kamar_asrama(): void
    {
        tenancy()->initialize($this->tenantA);

        $blok = \Modules\Asrama\Models\BlokAsrama::create([
            'tenant_id' => $this->tenantA->getKey(),
            'nama_blok' => 'Blok A',
            'is_active' => true,
        ]);

        $kamar = \Modules\Asrama\Models\KamarAsrama::create([
            'tenant_id' => $this->tenantA->getKey(),
            'blok_id' => $blok->id,
            'nomor_kamar' => 'A-101',
            'kapasitas' => 2,
            'is_active' => true,
        ]);

        $candidate = $this->makeAcceptedCandidate('PPDB-2026-206', 'Yusuf');

        $updated = app(PpdbReregistrationService::class)->register($candidate, [
            'tahun_angkatan' => '2026/2027',
            'kamar_asrama_id' => $kamar->id,
        ]);

        $penempatan = \Modules\Asrama\Models\PenempatanAsrama::query()
            ->where('santri_id', $updated->santri_id)
            ->where('is_current', true)
            ->first();

        $this->assertNotNull($penempatan, 'Santri harus tertempatkan ke kamar saat daftar ulang.');
        $this->assertSame($kamar->id, (int) $penempatan->kamar_id);

        tenancy()->end();
    }

    public function test_register_menolak_kamar_penuh(): void
    {
        tenancy()->initialize($this->tenantA);

        $blok = \Modules\Asrama\Models\BlokAsrama::create([
            'tenant_id' => $this->tenantA->getKey(),
            'nama_blok' => 'Blok B',
            'is_active' => true,
        ]);
        $kamar = \Modules\Asrama\Models\KamarAsrama::create([
            'tenant_id' => $this->tenantA->getKey(),
            'blok_id' => $blok->id,
            'nomor_kamar' => 'B-101',
            'kapasitas' => 1,
            'is_active' => true,
        ]);

        // Isi kamar hingga penuh lewat santri lain.
        $santriLama = Santri::create([
            'tenant_id' => $this->tenantA->getKey(),
            'nis' => 'S20260777',
            'nama' => 'Santri Penghuni',
            'tanggal_lahir' => '2010-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);
        \Modules\Asrama\Models\PenempatanAsrama::create([
            'tenant_id' => $this->tenantA->getKey(),
            'santri_id' => $santriLama->id,
            'kamar_id' => $kamar->id,
            'tanggal_masuk' => now()->toDateString(),
            'is_current' => true,
            'status' => 'aktif',
        ]);

        $candidate = $this->makeAcceptedCandidate('PPDB-2026-207', 'Idris');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('penuh');

        app(PpdbReregistrationService::class)->register($candidate, [
            'tahun_angkatan' => '2026/2027',
            'kamar_asrama_id' => $kamar->id,
        ]);

        tenancy()->end();
    }

    private function makeAcceptedCandidate(string $nomor, string $nama, string $gender = 'L'): CalonSantri
    {
        $candidate = CalonSantri::create([
            'nomor_pendaftaran' => $nomor,
            'nama' => $nama,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => $gender,
            'status_pendaftaran' => 'lulus',
        ]);

        HasilUjian::create([
            'calon_santri_id' => $candidate->id,
            'kategori_ujian' => 'tes_tulis',
            'nilai' => 90,
        ]);

        return $candidate;
    }
}
