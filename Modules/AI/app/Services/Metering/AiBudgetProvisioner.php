<?php

namespace Modules\AI\Services\Metering;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\AI\Models\AiCreditLedger;
use Modules\AI\Models\AiTenantBudget;
use Throwable;

/**
 * T-A002 — provisioning metering AI untuk tenant baru & eksisting:
 *  - baris ai_tenant_budget default (anggaran NULL = tanpa batas, alert 80%);
 *  - saldo awal ledger: alokasi 0 kredit (idempoten via idempotency_key unik).
 *
 * Dipanggil TenantProvisioner (jadi ikut tenant:create & tenant:reprovision).
 * Idempoten & toleran: tabel central belum termigrasi → log peringatan,
 * provisioning tenant TIDAK gagal karena fitur add-on AI.
 */
class AiBudgetProvisioner
{
    public function provision(string $tenantId): void
    {
        try {
            $central = (string) config('tenancy.database.central_connection', 'pgsql');

            if (! Schema::connection($central)->hasTable('ai_tenant_budget')
                || ! Schema::connection($central)->hasTable('ai_credit_ledger')) {
                Log::warning('AiBudgetProvisioner: tabel metering AI belum termigrasi di central — dilewati.', [
                    'tenant_id' => $tenantId,
                ]);

                return;
            }

            AiTenantBudget::query()->insertOrIgnore([
                'tenant_id' => $tenantId,
                'anggaran_kredit_bulanan' => null, // tanpa batas
                'ambang_alert_persen' => (int) config('ai.metering.ambang_alert_persen', 80),
                'periode_berjalan' => now()->startOfMonth()->toDateString(),
                'terpakai_periode' => 0,
                'kill_switch_aktif' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Saldo awal: alokasi 0. Kunci deterministik per tenant → reprovision
            // berkali-kali tetap 1 baris (ledger append-only, jangan dobel).
            AiCreditLedger::query()->insertOrIgnore([
                'tenant_id' => $tenantId,
                'jenis' => AiCreditLedger::JENIS_ALOKASI,
                'jumlah_kredit' => 0,
                'saldo_setelah' => 0,
                'referensi' => 'provisioning',
                'idempotency_key' => "alokasi-awal-{$tenantId}",
                'keterangan' => 'Saldo awal kredit AI (provisioning tenant)',
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('AiBudgetProvisioner: gagal menyiapkan budget AI — provisioning tenant tetap lanjut.', [
                'tenant_id' => $tenantId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
