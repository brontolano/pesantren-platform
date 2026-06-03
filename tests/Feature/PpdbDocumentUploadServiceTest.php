<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Services\PpdbDocumentUploadService;
use RuntimeException;
use Tests\TestCase;

class PpdbDocumentUploadServiceTest extends TestCase
{
    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'pgsql');
        Config::set('database.connections.pgsql.host', '127.0.0.1');
        Config::set('database.connections.pgsql.port', '5432');
        Config::set('database.connections.pgsql.database', 'pesantren_platform_test');
        Config::set('database.connections.pgsql.username', 'postgres');
        Config::set('database.connections.pgsql.password', 'secret');

        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->tenantA = Tenant::create([
            'id' => 'ppdb-doc-a',
            'name' => 'Pesantren A',
            'is_active' => true,
        ]);
        $this->tenantA->domains()->create(['domain' => 'ppdb-doc-a']);

        $this->tenantB = Tenant::create([
            'id' => 'ppdb-doc-b',
            'name' => 'Pesantren B',
            'is_active' => true,
        ]);
        $this->tenantB->domains()->create(['domain' => 'ppdb-doc-b']);

        Storage::fake('local');
    }

    public function test_upload_stores_document_and_metadata_for_candidate(): void
    {
        tenancy()->initialize($this->tenantA);

        $calon = CalonSantri::create([
            'nomor_pendaftaran' => 'PPDB-2026-001',
            'nama' => 'Ahmad',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status_pendaftaran' => 'baru',
        ]);

        $file = UploadedFile::fake()->create('ijazah-santri.pdf', 256, 'application/pdf');

        $dokumen = app(PpdbDocumentUploadService::class)->upload($calon, 'ijazah', $file, 'local');

        $this->assertSame($calon->id, $dokumen->calon_santri_id);
        $this->assertSame('ijazah', $dokumen->jenis_dokumen);
        $this->assertSame('local', $dokumen->metadata['disk']);
        $this->assertSame('ijazah-santri.pdf', $dokumen->metadata['original_name']);
        Storage::disk('local')->assertExists($dokumen->file_url);

        tenancy()->end();
    }

    public function test_upload_is_isolated_per_tenant(): void
    {
        tenancy()->initialize($this->tenantA);

        CalonSantri::create([
            'nomor_pendaftaran' => 'PPDB-2026-001',
            'nama' => 'Tenant A',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status_pendaftaran' => 'baru',
        ]);

        tenancy()->end();
        tenancy()->initialize($this->tenantB);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $file = UploadedFile::fake()->create('foto.png', 128, 'image/png');
        app(PpdbDocumentUploadService::class)->upload(1, 'foto', $file, 'local');

        tenancy()->end();
    }

    public function test_upload_rejects_unsupported_document_type(): void
    {
        tenancy()->initialize($this->tenantA);

        $calon = CalonSantri::create([
            'nomor_pendaftaran' => 'PPDB-2026-001',
            'nama' => 'Fatimah',
            'tanggal_lahir' => '2012-02-02',
            'jenis_kelamin' => 'P',
            'status_pendaftaran' => 'baru',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Jenis dokumen PPDB tidak didukung');

        $file = UploadedFile::fake()->create('sertifikat.pdf', 128, 'application/pdf');
        app(PpdbDocumentUploadService::class)->upload($calon, 'sertifikat', $file, 'local');

        tenancy()->end();
    }

    public function test_upload_rejects_unsupported_file_extension(): void
    {
        tenancy()->initialize($this->tenantA);

        $calon = CalonSantri::create([
            'nomor_pendaftaran' => 'PPDB-2026-001',
            'nama' => 'Maryam',
            'tanggal_lahir' => '2012-03-03',
            'jenis_kelamin' => 'P',
            'status_pendaftaran' => 'baru',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Format file dokumen PPDB tidak didukung');

        $file = UploadedFile::fake()->create('akta.exe', 64, 'application/octet-stream');
        app(PpdbDocumentUploadService::class)->upload($calon, 'akta_kelahiran', $file, 'local');

        tenancy()->end();
    }

    public function test_upload_requires_active_tenant_context(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant context belum aktif');

        $file = UploadedFile::fake()->create('ijazah.pdf', 128, 'application/pdf');
        app(PpdbDocumentUploadService::class)->upload(1, 'ijazah', $file, 'local');
    }
}
