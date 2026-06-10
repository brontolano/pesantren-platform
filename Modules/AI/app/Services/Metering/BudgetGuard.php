<?php

namespace Modules\AI\Services\Metering;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Modules\AI\Models\AiTenantBudget;
use Modules\Notifikasi\Models\LogNotifikasi;
use Throwable;

/**
 * T-A002 — penjaga anggaran AI per tenant.
 *
 * Dipanggil AiGateway SEBELUM memanggil provider: kill-switch aktif →
 * gateway langsung membalas pesan santun "anggaran habis" TANPA HTTP ke
 * provider. Notifikasi alert ambang (sekali per periode) lewat pola log
 * notifikasi database eksisting Modules/Notifikasi (LogNotifikasi, channel
 * 'database') — BUKAN jalur WA baru.
 */
class BudgetGuard
{
    /**
     * Status anggaran tenant. Read-only (tanpa lock — keputusan pre-check;
     * pencatatan definitif tetap diserialisasi CreditMeter::catat).
     */
    public function cek(string $tenantId): BudgetStatus
    {
        $budget = AiTenantBudget::query()->where('tenant_id', $tenantId)->first();

        if ($budget === null) {
            // Belum diprovision = tanpa batas (default tenant baru).
            return BudgetStatus::permisif();
        }

        // Rollover virtual: periode budget sudah lewat → bulan baru dimulai
        // bersih (reset definitif ditulis CreditMeter saat pemakaian pertama).
        if ($budget->periode_berjalan !== null && $budget->periode_berjalan->lt(now()->startOfMonth())) {
            return BudgetStatus::permisif();
        }

        $anggaran = $budget->anggaran_kredit_bulanan;
        $persen = ($anggaran !== null && (float) $anggaran > 0)
            ? round((float) $budget->terpakai_periode / (float) $anggaran * 100, 2)
            : 0.0;

        return new BudgetStatus(
            boleh: ! $budget->kill_switch_aktif,
            persenTerpakai: $persen,
            killSwitch: $budget->kill_switch_aktif,
        );
    }

    /**
     * Varian aman-jalur-utama: kegagalan cek anggaran TIDAK menggagalkan
     * permintaan (fail-open + log keras) — pagar anggaran tidak boleh jadi
     * single point of failure fitur AI.
     */
    public function cekOrLog(string $tenantId): BudgetStatus
    {
        try {
            return $this->cek($tenantId);
        } catch (Throwable $e) {
            Log::error('BudgetGuard: GAGAL memeriksa anggaran — permintaan diloloskan (fail-open).', [
                'tenant_id' => $tenantId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return BudgetStatus::permisif();
        }
    }

    /**
     * Notifikasi database ke admin tenant: pemakaian melewati ambang alert.
     * Dipanggil CreditMeter SEKALI per periode (gated alert_terkirim_pada).
     * Best-effort: kegagalan hanya ter-log, tidak melempar.
     */
    public function kirimAlertAnggaran(string $tenantId, float $persen): void
    {
        $tulis = static function () use ($tenantId, $persen): void {
            LogNotifikasi::query()->create([
                'tenant_id' => $tenantId,
                'jenis' => 'ai_anggaran',
                'channel' => 'database',
                'target' => 'admin_tenant',
                'status' => 'sent',
                'payload' => [
                    'judul' => 'Anggaran AI hampir habis',
                    'pesan' => sprintf(
                        'Pemakaian kredit AI bulan ini telah mencapai %s%% dari anggaran. '
                        .'Mohon tinjau pemakaian atau lakukan top-up agar layanan AI tidak terhenti.',
                        number_format($persen, 0, ',', '.'),
                    ),
                    'persen_terpakai' => $persen,
                ],
                'created_at' => now(),
            ]);
        };

        try {
            if (tenancy()->initialized && tenant()?->getTenantKey() === $tenantId) {
                $tulis();
            } else {
                Tenant::query()->findOrFail($tenantId)->run($tulis);
            }
        } catch (Throwable $e) {
            Log::error('BudgetGuard: GAGAL menulis notifikasi alert anggaran AI.', [
                'tenant_id' => $tenantId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
