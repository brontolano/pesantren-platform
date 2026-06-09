<?php

namespace Tests\Feature;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\Auditable;
use Modules\Core\Models\AuditLog;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F2 — Audit Trail. Model uji ber-Auditable "menumpang" tabel inventory_item.
 */
class AuditTrailTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'audit_log', 'inventory_item',
        ]);

        $this->truncatePpdbTables(['audit_log', 'inventory_item', 'domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    private function model(): AuditableTestModel
    {
        return AuditableTestModel::create(['kode' => 'AU-'.str()->random(5), 'nama' => 'Awal', 'harga_jual' => 1000]);
    }

    public function test_create_tercatat(): void
    {
        $this->boot('au1');
        $m = $this->model();

        $log = AuditLog::query()->where('auditable_id', $m->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('create', $log->aksi);
        $this->assertSame('Awal', $log->perubahan['after']['nama'] ?? null);
        $this->assertArrayNotHasKey('created_at', $log->perubahan['after']); // dikecualikan
        tenancy()->end();
    }

    public function test_update_mencatat_before_after(): void
    {
        $this->boot('au2');
        $m = $this->model();
        AuditLog::query()->delete(); // bersihkan log create

        $m->update(['nama' => 'Diubah']);

        $log = AuditLog::query()->where('aksi', 'update')->first();
        $this->assertNotNull($log);
        $this->assertSame('Awal', $log->perubahan['before']['nama'] ?? null);
        $this->assertSame('Diubah', $log->perubahan['after']['nama'] ?? null);
        tenancy()->end();
    }

    public function test_delete_tercatat(): void
    {
        $this->boot('au3');
        $m = $this->model();
        $id = $m->id;
        $m->delete();

        $this->assertTrue(AuditLog::query()->where('auditable_id', $id)->where('aksi', 'delete')->exists());
        tenancy()->end();
    }

    public function test_aksi_domain_manual_dan_append_only(): void
    {
        $this->boot('au4');
        $m = $this->model();
        $m->tulisAudit('approve', [], ['status' => 'disetujui']);

        $log = AuditLog::query()->where('aksi', 'approve')->first();
        $this->assertNotNull($log);
        // append-only: AuditLog tak punya updated_at
        $this->assertNull($log->updated_at ?? null);
        tenancy()->end();
    }

    public function test_isolasi_tenant_audit(): void
    {
        $this->boot('auA');
        $this->model();
        $this->assertGreaterThanOrEqual(1, AuditLog::query()->count());
        tenancy()->end();

        $this->boot('auB');
        $this->assertSame(0, AuditLog::query()->count(), 'Audit tenant lain tak boleh terlihat.');
        tenancy()->end();
    }
}

/**
 * Model uji ber-Auditable — pakai tabel inventory_item.
 */
class AuditableTestModel extends Model
{
    use BelongsToTenant;
    use Auditable;

    protected $table = 'inventory_item';

    protected $guarded = [];

    public $timestamps = true;
}
