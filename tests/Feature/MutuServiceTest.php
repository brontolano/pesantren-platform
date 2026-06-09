<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\Mutu\Models\DokumenMutu;
use Modules\Mutu\Services\MutuService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * #27 (Mutu) — alur approval (state-machine) + versioning DokumenMutu.
 */
class MutuServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'dokumen_mutu', 'dokumen_mutu_versi',
        ]);

        $this->truncatePpdbTables(['dokumen_mutu_versi', 'dokumen_mutu', 'users', 'domains', 'tenants']);
    }

    private function bootTenant(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        return $tenant;
    }

    private function reviewer(): User
    {
        return User::withoutGlobalScopes()->create([
            'name' => 'Kepala Mutu',
            'email' => 'mutu@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);
    }

    private function buat(MutuService $svc): DokumenMutu
    {
        return $svc->buatDokumen([
            'judul' => 'SOP Penerimaan Santri',
            'jenis' => 'SOP',
            'regulasi_acuan' => 'KMA 939/2024',
        ]);
    }

    public function test_buat_dokumen_draft_versi_satu_dengan_snapshot(): void
    {
        $this->bootTenant('mutu1');
        $svc = new MutuService();

        $doc = $this->buat($svc);

        $this->assertSame('draft', $doc->status_approval);
        $this->assertSame(1, $doc->versi);
        $this->assertCount(1, $svc->riwayat($doc));
        $this->assertSame(1, $svc->riwayat($doc)->first()->versi);
    }

    public function test_alur_ajukan_lalu_setujui(): void
    {
        $this->bootTenant('mutu2');
        $svc = new MutuService();
        $reviewer = $this->reviewer();

        $doc = $this->buat($svc);

        $doc = $svc->ajukan($doc, $reviewer->id);
        $this->assertSame('diajukan', $doc->status_approval);
        $this->assertNotNull($doc->diajukan_oleh);

        $doc = $svc->setujui($doc, $reviewer->id);
        $this->assertSame('disetujui', $doc->status_approval);
        $this->assertSame($reviewer->id, $doc->direview_oleh);
    }

    public function test_tolak_menyimpan_alasan(): void
    {
        $this->bootTenant('mutu3');
        $svc = new MutuService();
        $reviewer = $this->reviewer();

        $doc = $svc->ajukan($this->buat($svc), $reviewer->id);
        $doc = $svc->tolak($doc, $reviewer->id, 'Regulasi acuan kedaluwarsa.');

        $this->assertSame('ditolak', $doc->status_approval);
        $this->assertSame('Regulasi acuan kedaluwarsa.', $doc->catatan_review);
    }

    public function test_ajukan_dokumen_non_draft_ditolak(): void
    {
        $this->bootTenant('mutu4');
        $svc = new MutuService();
        $reviewer = $this->reviewer();

        $doc = $svc->ajukan($this->buat($svc), $reviewer->id); // sudah diajukan

        $this->expectException(InvalidArgumentException::class);
        $svc->ajukan($doc, $reviewer->id);
    }

    public function test_setujui_dokumen_belum_diajukan_ditolak(): void
    {
        $this->bootTenant('mutu5');
        $svc = new MutuService();

        $doc = $this->buat($svc); // masih draft

        $this->expectException(InvalidArgumentException::class);
        $svc->setujui($doc, $this->reviewer()->id);
    }

    public function test_revisi_menaikkan_versi_dan_reset_draft(): void
    {
        $this->bootTenant('mutu6');
        $svc = new MutuService();
        $reviewer = $this->reviewer();

        $doc = $svc->setujui($svc->ajukan($this->buat($svc), $reviewer->id), $reviewer->id);
        $this->assertSame('disetujui', $doc->status_approval);

        $doc = $svc->revisi($doc, ['judul' => 'SOP Penerimaan Santri (Revisi)']);

        $this->assertSame(2, $doc->versi);
        $this->assertSame('draft', $doc->status_approval);
        $this->assertNull($doc->direview_oleh, 'Field review harus direset pada revisi.');
        $this->assertSame('SOP Penerimaan Santri (Revisi)', $doc->judul);

        // Riwayat memuat kedua versi.
        $riwayat = $svc->riwayat($doc);
        $this->assertCount(2, $riwayat);
        $this->assertSame([1, 2], $riwayat->pluck('versi')->all());
    }

    public function test_isolasi_tenant_dokumen_a_tidak_terlihat_di_b(): void
    {
        $this->bootTenant('mutuA');
        $svc = new MutuService();
        $this->buat($svc);
        $this->assertSame(1, DokumenMutu::query()->count());

        tenancy()->end();

        $this->bootTenant('mutuB');
        $this->assertSame(0, DokumenMutu::query()->count(), 'Dokumen tenant A tak boleh terlihat di tenant B.');
    }
}
