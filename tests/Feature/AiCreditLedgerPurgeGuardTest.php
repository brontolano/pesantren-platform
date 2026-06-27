<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * R1 (hardening 2026-06-27): trigger append-only ai_credit_ledger yang sadar-konteks.
 *
 * Imutabilitas keuangan tetap dijaga: UPDATE selalu ditolak, DELETE ditolak pada
 * operasi biasa. TAPI penghapusan tenant terotorisasi (GUC app.ledger_purge='on')
 * boleh memurnikan ledger — menyelesaikan kontradiksi FK cascadeOnDelete vs
 * trigger tolak-semua yang dulu membuat Tenant::delete() gagal membingungkan.
 */
class AiCreditLedgerPurgeGuardTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['ai_credit_ledger', 'tenants', 'domains']);
        // TRUNCATE tak memicu trigger BEFORE DELETE (row-level) → aman bersihkan.
        $this->truncatePpdbTables(['ai_credit_ledger', 'domains', 'tenants']);
        // ai_credit_ledger.tenant_id berreferensi ke tenants(id) → butuh baris tenant.
        $this->tenantId = $this->createTestTenant('led-'.Str::lower(Str::random(6)), 'Uji Ledger')->id;
    }

    private function seedRow(): int
    {
        return (int) DB::table('ai_credit_ledger')->insertGetId([
            'tenant_id' => $this->tenantId,
            'jenis' => 'alokasi',
            'jumlah_kredit' => 100,
            'saldo_setelah' => 100,
            'idempotency_key' => 'idem-'.Str::lower(Str::random(12)),
            'created_at' => now(),
        ]);
    }

    public function test_update_selalu_ditolak(): void
    {
        $id = $this->seedRow();
        $this->expectException(QueryException::class);
        DB::table('ai_credit_ledger')->where('id', $id)->update(['jumlah_kredit' => 999]);
    }

    public function test_delete_ditolak_tanpa_purge(): void
    {
        $id = $this->seedRow();
        $this->expectException(QueryException::class);
        DB::table('ai_credit_ledger')->where('id', $id)->delete();
    }

    public function test_delete_diizinkan_dalam_konteks_purge_terotorisasi(): void
    {
        $id = $this->seedRow();

        DB::transaction(function () use ($id): void {
            DB::statement("SET LOCAL app.ledger_purge = 'on'");
            DB::table('ai_credit_ledger')->where('id', $id)->delete();
        });

        $this->assertDatabaseMissing('ai_credit_ledger', ['id' => $id]);
    }
}
