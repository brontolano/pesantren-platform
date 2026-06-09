<?php

namespace Tests\Feature;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Modules\Core\Concerns\Approvable;
use Modules\Core\Events\ApprovalDiputuskan;
use Modules\Core\Exceptions\PersetujuanBelumLengkapException;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\ApprovalRule;
use Modules\Core\Services\ApprovalService;
use RuntimeException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F1 — Approval Workflow Engine. Model uji "menumpang" tabel inventory_item.
 */
class ApprovalEngineTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'approval_rule', 'approval_request', 'inventory_item',
        ]);

        $this->truncatePpdbTables(['approval_request', 'approval_rule', 'inventory_item', 'domains', 'tenants']);
    }

    private int $pemintaId;

    private int $penyetujuId;

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->pemintaId = (int) User::factory()->create()->id;
        $this->penyetujuId = (int) User::factory()->create()->id;
    }

    private function rule(array $attr = []): ApprovalRule
    {
        return ApprovalRule::create(array_merge([
            'approvable_type' => ApprovableTestModel::class,
            'nama' => 'Aturan uji',
            'field' => 'harga_jual',
            'operator' => '>=',
            'nilai' => 1000000,
            'approver_role' => 'bendahara',
            'mode' => 'wajib',
            'urutan' => 1,
            'aktif' => true,
        ], $attr));
    }

    private function model(float $hargaJual): ApprovableTestModel
    {
        return ApprovableTestModel::create([
            'kode' => 'AP-'.str()->random(5), 'nama' => 'Uji', 'harga_jual' => $hargaJual,
        ]);
    }

    public function test_terpicu_mengikuti_operator(): void
    {
        $this->boot('ap1');
        $r = $this->rule(['operator' => '>=', 'nilai' => 1000000]);
        $this->assertTrue($r->terpicu($this->model(1000000)));
        $this->assertTrue($r->terpicu($this->model(2000000)));
        $this->assertFalse($r->terpicu($this->model(500000)));

        $this->assertTrue($this->rule(['operator' => '<', 'nilai' => 100])->terpicu($this->model(50)));
        tenancy()->end();
    }

    public function test_ajukan_membuat_request_idempoten(): void
    {
        $this->boot('ap2');
        $this->rule();
        $m = $this->model(5000000);

        $req1 = $m->ajukanPersetujuan($this->pemintaId);
        $req2 = $m->ajukanPersetujuan($this->pemintaId);

        $this->assertNotNull($req1);
        $this->assertSame($req1->id, $req2->id, 'Ajukan kedua tak boleh membuat request baru.');
        $this->assertSame('menunggu', $req1->status);
        $this->assertSame(1, ApprovalRequest::query()->count());
        tenancy()->end();
    }

    public function test_tanpa_aturan_terpicu_tidak_butuh_persetujuan(): void
    {
        $this->boot('ap3');
        $this->rule(['nilai' => 9000000]); // ambang tinggi
        $m = $this->model(1000000); // di bawah ambang

        $this->assertFalse($m->butuhPersetujuan());
        $this->assertNull($m->ajukanPersetujuan($this->pemintaId));
        $m->pastikanBolehLanjut(); // tak melempar
        $this->assertTrue(true);
        tenancy()->end();
    }

    public function test_setujui_memancarkan_event_dan_membuka_pagar(): void
    {
        $this->boot('ap4');
        Event::fake([ApprovalDiputuskan::class]);
        $this->rule();
        $m = $this->model(5000000);
        $req = $m->ajukanPersetujuan($this->pemintaId);

        $this->expectExceptionMessageMatchesNothingHelper($m); // pagar tertutup sebelum disetujui

        app(ApprovalService::class)->setujui($req, $this->penyetujuId);

        Event::assertDispatched(ApprovalDiputuskan::class);
        $this->assertSame('disetujui', $req->fresh()->status);
        $this->assertTrue($m->sudahDisetujui());
        $m->pastikanBolehLanjut(); // kini lolos
        tenancy()->end();
    }

    private function expectExceptionMessageMatchesNothingHelper(ApprovableTestModel $m): void
    {
        try {
            $m->pastikanBolehLanjut();
            $this->fail('Seharusnya melempar sebelum disetujui.');
        } catch (PersetujuanBelumLengkapException $e) {
            $this->assertStringContainsString('persetujuan', strtolower($e->getMessage()));
        }
    }

    public function test_tolak_menyimpan_alasan(): void
    {
        $this->boot('ap5');
        $this->rule();
        $m = $this->model(5000000);
        $req = $m->ajukanPersetujuan($this->pemintaId);

        app(ApprovalService::class)->tolak($req, $this->penyetujuId, 'Tidak sesuai anggaran');

        $this->assertSame('ditolak', $req->fresh()->status);
        $this->assertSame('Tidak sesuai anggaran', $req->fresh()->alasan);
        tenancy()->end();
    }

    public function test_keputusan_ganda_ditolak(): void
    {
        $this->boot('ap6');
        $this->rule();
        $req = $this->model(5000000)->ajukanPersetujuan($this->pemintaId);
        app(ApprovalService::class)->setujui($req, $this->penyetujuId);

        $this->expectException(RuntimeException::class);
        app(ApprovalService::class)->setujui($req->fresh(), $this->penyetujuId);
        tenancy()->end();
    }

    public function test_isolasi_tenant_request(): void
    {
        $this->boot('apA');
        $this->rule();
        $this->model(5000000)->ajukanPersetujuan($this->pemintaId);
        $this->assertSame(1, ApprovalRequest::query()->count());
        tenancy()->end();

        $this->boot('apB');
        $this->assertSame(0, ApprovalRequest::query()->count(), 'Request tenant lain tak boleh terlihat.');
        tenancy()->end();
    }
}

/**
 * Model uji ber-Approvable — pakai tabel inventory_item (kolom id/tenant_id/kode/nama/harga_jual).
 */
class ApprovableTestModel extends Model
{
    use BelongsToTenant;
    use Approvable;

    protected $table = 'inventory_item';

    protected $guarded = [];

    public $timestamps = true;
}
