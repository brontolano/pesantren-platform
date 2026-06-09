<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\HasDocuments;
use Modules\Core\Models\Document;
use Modules\Core\Services\DocumentService;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Model fixture: menumpang tabel `santri` hanya untuk menguji trait HasDocuments
 * tanpa mengubah model produksi (consumer asli diwire di gelombang G terpisah).
 */
class DocumentableTestModel extends Model
{
    use HasDocuments;

    protected $table = 'santri';

    protected $guarded = [];

    public $timestamps = true;
}

/**
 * F5 — Repositori Dokumen. Menguji versioning otomatis DocumentService,
 * pemisahan versi per (documentable, kategori), trait HasDocuments, dan
 * isolasi tenant.
 */
class DocumentRepositoryTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'document',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    private function santri(string $nama): Santri
    {
        return Santri::create([
            'nis' => 'DOC-'.str()->random(5), 'nama' => $nama,
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
    }

    public function test_versioning_otomatis_per_kategori(): void
    {
        $this->boot('doc1');
        $s = $this->santri('Andi');
        $svc = app(DocumentService::class);

        $v1 = $svc->simpan(['documentable_type' => Santri::class, 'documentable_id' => $s->id, 'kategori' => 'ijazah', 'judul' => 'Ijazah SD', 'path' => 'documents/a.pdf']);
        $v2 = $svc->simpan(['documentable_type' => Santri::class, 'documentable_id' => $s->id, 'kategori' => 'ijazah', 'judul' => 'Ijazah SD (revisi)', 'path' => 'documents/b.pdf']);
        // Kategori berbeda → versi mulai dari 1 lagi.
        $lain = $svc->simpan(['documentable_type' => Santri::class, 'documentable_id' => $s->id, 'kategori' => 'sertifikat', 'judul' => 'Sertifikat Tahfidz', 'path' => 'documents/c.pdf']);

        $this->assertSame(1, $v1->versi);
        $this->assertSame(2, $v2->versi);
        $this->assertSame(1, $lain->versi);
        tenancy()->end();
    }

    public function test_trait_lampirkan_dan_dokumen_terbaru(): void
    {
        $this->boot('doc2');
        $this->santri('Budi');
        $m = DocumentableTestModel::query()->first();

        $m->lampirkanDokumen(['kategori' => 'medis', 'judul' => 'Rekam Medis v1', 'path' => 'documents/m1.pdf']);
        $m->lampirkanDokumen(['kategori' => 'medis', 'judul' => 'Rekam Medis v2', 'path' => 'documents/m2.pdf']);

        $this->assertCount(2, $m->documents);
        $terbaru = $m->dokumenTerbaru('medis');
        $this->assertSame('Rekam Medis v2', $terbaru->judul);
        $this->assertSame(2, $terbaru->versi);
        tenancy()->end();
    }

    public function test_dokumen_standalone_tanpa_documentable(): void
    {
        $this->boot('doc3');
        $svc = app(DocumentService::class);

        $a = $svc->simpan(['kategori' => 'mou', 'judul' => 'MoU Mitra A', 'path' => 'documents/mou-a.pdf']);
        $b = $svc->simpan(['kategori' => 'mou', 'judul' => 'MoU Mitra A revisi', 'path' => 'documents/mou-b.pdf']);

        $this->assertNull($a->documentable_type);
        $this->assertSame(1, $a->versi);
        $this->assertSame(2, $b->versi);
        tenancy()->end();
    }

    public function test_url_pakai_pola_tenant_storage(): void
    {
        $this->boot('doc4');
        $d = app(DocumentService::class)->simpan(['kategori' => 'sk', 'judul' => 'SK Pengangkatan', 'path' => 'documents/sk.pdf']);
        $this->assertSame('/_tenant-storage/documents/sk.pdf', $d->url);
        tenancy()->end();
    }

    public function test_isolasi_tenant(): void
    {
        $this->boot('docA');
        app(DocumentService::class)->simpan(['kategori' => 'lainnya', 'judul' => 'Berkas A', 'path' => 'documents/x.pdf']);
        $this->assertSame(1, Document::query()->count());
        tenancy()->end();

        $this->boot('docB');
        $this->assertSame(0, Document::query()->count());
        tenancy()->end();
    }
}
