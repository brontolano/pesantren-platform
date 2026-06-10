<?php

namespace Tests\Feature;

use Modules\Core\Models\AuditLog;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Services\JurnalService;
use Modules\Pajak\Models\TarifPajak;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G1-02 — Rollout Audit Trail (F2). Membuktikan model inti kini terekam otomatis
 * (create/update/delete) ke audit_log, termasuk version-history TarifPajak (G1-05),
 * tanpa memecah operasi domain, dengan isolasi tenant.
 */
class AuditRolloutTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'audit_log',
            'tarif_pajak', 'akun', 'jurnal', 'jurnal_baris', 'sumber_dana', 'periode_akuntansi',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    private function auditCount(string $type, string $aksi): int
    {
        return AuditLog::query()->where('auditable_type', $type)->where('aksi', $aksi)->count();
    }

    public function test_create_update_delete_tarif_teraudit_version_history(): void
    {
        $this->boot('aud1');

        // CREATE
        $tarif = TarifPajak::create([
            'kode' => 'ppn', 'nama' => 'PPN', 'tarif_persen' => 11.00, 'ambang_min' => 1000000,
            'dasar' => 'PMK 59/2022', 'is_active' => true,
        ]);
        $this->assertSame(1, $this->auditCount(TarifPajak::class, 'create'));

        // UPDATE (version history G1-05)
        $tarif->update(['tarif_persen' => 12.00]);
        $this->assertSame(1, $this->auditCount(TarifPajak::class, 'update'));
        $log = AuditLog::query()->where('auditable_type', TarifPajak::class)->where('aksi', 'update')->latest('id')->first();
        $this->assertArrayHasKey('tarif_persen', $log->perubahan['after']);
        $this->assertArrayHasKey('tarif_persen', $log->perubahan['before']);

        // DELETE
        $tarif->delete();
        $this->assertSame(1, $this->auditCount(TarifPajak::class, 'delete'));
        tenancy()->end();
    }

    public function test_jurnal_create_teraudit_tanpa_memecah_posting(): void
    {
        $this->boot('aud2');
        (new LedgerSeeder)->run();

        $jurnal = app(JurnalService::class)->postJurnal([
            'tanggal' => now()->toDateString(), 'deskripsi' => 'Uji audit', 'tipe_sumber' => 'manual',
            'allow_duplicate' => true,
            'baris' => [
                ['akun_id' => (int) Akun::where('kode', '1-1000')->value('id'), 'debit' => 100000, 'kredit' => 0],
                ['akun_id' => (int) Akun::where('kode', '4-1000')->value('id'), 'debit' => 0, 'kredit' => 100000],
            ],
        ]);

        // Posting tetap berhasil + teraudit.
        $this->assertSame('posted', $jurnal->fresh()->status);
        $this->assertGreaterThanOrEqual(1, $this->auditCount(Jurnal::class, 'create'));
        // Jurnal immutable → tak ada aksi update/delete.
        $this->assertSame(0, $this->auditCount(Jurnal::class, 'update'));
        tenancy()->end();
    }

    public function test_perubahan_mencatat_before_after_dan_actor_null_aman(): void
    {
        $this->boot('aud3');
        $tarif = TarifPajak::create(['kode' => 'pph22', 'nama' => 'PPh22', 'tarif_persen' => 1.5, 'is_active' => true]);
        $tarif->update(['nama' => 'PPh Pasal 22']);

        $log = AuditLog::query()->where('aksi', 'update')->latest('id')->first();
        $this->assertSame('PPh22', $log->perubahan['before']['nama']);
        $this->assertSame('PPh Pasal 22', $log->perubahan['after']['nama']);
        $this->assertNull($log->actor_id); // tanpa auth di test → null, FK nullable aman
        tenancy()->end();
    }

    public function test_isolasi_tenant(): void
    {
        $this->boot('audA');
        TarifPajak::create(['kode' => 'ppn', 'nama' => 'PPN', 'tarif_persen' => 11, 'is_active' => true]);
        $this->assertSame(1, AuditLog::query()->count());
        tenancy()->end();

        $this->boot('audB');
        $this->assertSame(0, AuditLog::query()->count());
        tenancy()->end();
    }
}
