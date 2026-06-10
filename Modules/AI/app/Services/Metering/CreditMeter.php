<?php

namespace Modules\AI\Services\Metering;

use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\AI\Models\AiCreditLedger;
use Modules\AI\Models\AiTenantBudget;
use Modules\AI\Models\AiUsageEvent;
use RuntimeException;
use Throwable;

/**
 * T-A002 — meteran kredit AI per tenant (pagar #5: SEMUA panggilan termeter,
 * termasuk provider lokal biaya ≈ 0 dan degradasi NullProvider = 0 kredit).
 *
 * Atomisitas (tugas uang, pelajaran modul Keuangan):
 *  - SELECT ... FOR UPDATE pada baris ai_tenant_budget menserialisasi semua
 *    penulisan ledger satu tenant → saldo_setelah selalu kontinu tanpa race;
 *  - idempotency_key NOT NULL UNIQUE pada ledger & usage_event (pelajaran
 *    PR#49: kunci NULL tidak ter-dedup di SQL) — duplikat = no-op, kembalikan
 *    baris lama, saldo TIDAK terpotong dobel.
 */
class CreditMeter
{
    public function __construct(private readonly BudgetGuard $guard) {}

    /**
     * Hitung kredit dari tarif per tier (config ai.metering.tarif, per 1000
     * token). Degradasi (NullProvider) = 0 kredit — tapi pemanggil TETAP wajib
     * mencatat eventnya (pagar #5).
     */
    public function hitungKredit(string $tier, ?int $tokenMasuk, ?int $tokenKeluar, bool $degraded = false): float
    {
        if ($degraded) {
            return 0.0;
        }

        $tarif = config("ai.metering.tarif.{$tier}");

        if (! is_array($tarif)) {
            // Tier tanpa tarif = lubang billing — pakai tarif standar dan
            // teriak keras di log, jangan diam-diam menggratiskan.
            Log::error("CreditMeter: tarif tier '{$tier}' tidak terdefinisi — memakai tarif 'standar'.");
            $tarif = (array) config('ai.metering.tarif.standar', ['masuk' => 0.5, 'keluar' => 1.0]);
        }

        $kredit = ((int) $tokenMasuk * (float) ($tarif['masuk'] ?? 0)
            + (int) $tokenKeluar * (float) ($tarif['keluar'] ?? 0)) / 1000;

        return round($kredit, 4);
    }

    /**
     * Catat satu pemakaian: potong saldo ledger CENTRAL secara atomik
     * (FOR UPDATE pada baris budget) + update terpakai_periode + tulis kopi
     * ai_usage_event di DB tenant. Idempoten: kunci duplikat → no-op,
     * kembalikan baris ledger lama.
     *
     * @param  array{agent_key: string, channel?: string, provider: string, model: string,
     *               tier: string, token_masuk?: ?int, token_keluar?: ?int,
     *               kredit: float, degraded?: bool, referensi?: ?string}  $data
     */
    public function catat(string $tenantId, array $data, string $idempotencyKey): AiCreditLedger
    {
        if (trim($idempotencyKey) === '') {
            // Pelajaran PR#49: operasi uang TANPA kunci tidak boleh lewat.
            throw new RuntimeException('CreditMeter::catat membutuhkan idempotency_key non-kosong.');
        }

        $kredit = round((float) $data['kredit'], 4);
        $alertBaru = false;
        $persen = 0.0;
        $duplikat = false;

        try {
            $ledger = DB::connection($this->centralConnection())->transaction(function () use ($tenantId, $data, $idempotencyKey, $kredit, &$alertBaru, &$persen): AiCreditLedger {
                $budget = $this->lockBudgetRow($tenantId);
                $this->rolloverBilaPeriodeBaru($budget);

                // Saldo terakhir — aman tanpa lock tambahan: baris budget yang
                // ter-FOR UPDATE sudah menserialisasi seluruh tulis ledger tenant ini.
                $saldoTerakhir = (float) (AiCreditLedger::query()
                    ->where('tenant_id', $tenantId)
                    ->orderByDesc('id')
                    ->value('saldo_setelah') ?? 0);

                $ledger = AiCreditLedger::query()->create([
                    'tenant_id' => $tenantId,
                    'jenis' => AiCreditLedger::JENIS_PEMAKAIAN,
                    'jumlah_kredit' => -$kredit,
                    'saldo_setelah' => round($saldoTerakhir - $kredit, 4),
                    'referensi' => $data['referensi'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                    'keterangan' => sprintf(
                        'Pemakaian %s (%s/%s, tier %s)',
                        $data['agent_key'],
                        $data['provider'],
                        $data['model'],
                        $data['tier'],
                    ),
                    'created_at' => now(),
                ]);

                $budget->terpakai_periode = round((float) $budget->terpakai_periode + $kredit, 4);

                $anggaran = $budget->anggaran_kredit_bulanan;
                if ($anggaran !== null && (float) $anggaran > 0) {
                    $persen = round((float) $budget->terpakai_periode / (float) $anggaran * 100, 2);

                    if ($persen >= 100 && ! $budget->kill_switch_aktif) {
                        $budget->kill_switch_aktif = true;
                    }

                    if ($persen >= $budget->ambang_alert_persen && $budget->alert_terkirim_pada === null) {
                        $budget->alert_terkirim_pada = now();
                        $alertBaru = true; // SEKALI per periode
                    }
                }

                $budget->save();

                return $ledger;
            });
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            // Duplikat idempotency_key → no-op, kembalikan baris lama
            // (saldo & terpakai_periode TIDAK berubah — transaksi sudah rollback).
            $duplikat = true;
            $ledger = AiCreditLedger::query()
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();
        }

        if (! $duplikat) {
            $this->tulisUsageEvent($tenantId, $data, $idempotencyKey, $kredit);

            if ($alertBaru) {
                $this->guard->kirimAlertAnggaran($tenantId, $persen);
            }
        }

        return $ledger;
    }

    /**
     * Varian aman-jalur-utama (pola AuditRecorder::recordOrLog): kegagalan
     * metering dicatat KERAS ke log, TIDAK menggagalkan permintaan utama.
     */
    public function catatOrLog(string $tenantId, array $data, string $idempotencyKey): ?AiCreditLedger
    {
        try {
            return $this->catat($tenantId, $data, $idempotencyKey);
        } catch (Throwable $e) {
            Log::error('CreditMeter: GAGAL mencatat kredit — permintaan utama tetap dilanjutkan.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'agent_key' => $data['agent_key'] ?? null,
                'idempotency_key' => $idempotencyKey,
            ]);

            return null;
        }
    }

    /**
     * Ambil baris budget tenant dengan FOR UPDATE; buat default dulu bila
     * belum ada (insertOrIgnore — aman terhadap race INSERT ganda).
     */
    private function lockBudgetRow(string $tenantId): AiTenantBudget
    {
        $budget = AiTenantBudget::query()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->first();

        if ($budget !== null) {
            return $budget;
        }

        AiTenantBudget::query()->insertOrIgnore([
            'tenant_id' => $tenantId,
            'anggaran_kredit_bulanan' => null, // default tanpa batas
            'ambang_alert_persen' => (int) config('ai.metering.ambang_alert_persen', 80),
            'periode_berjalan' => now()->startOfMonth()->toDateString(),
            'terpakai_periode' => 0,
            'kill_switch_aktif' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return AiTenantBudget::query()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Rollover bulan: periode_berjalan < awal bulan kini → reset pemakaian,
     * nonaktifkan kill-switch, kosongkan penanda alert. (Dipanggil saat baris
     * sudah ter-lock; persist ikut $budget->save() pemanggil.)
     */
    private function rolloverBilaPeriodeBaru(AiTenantBudget $budget): void
    {
        $awalBulan = now()->startOfMonth();

        if ($budget->periode_berjalan !== null && $budget->periode_berjalan->lt($awalBulan)) {
            $budget->periode_berjalan = $awalBulan->toDateString();
            $budget->terpakai_periode = 0;
            $budget->kill_switch_aktif = false;
            $budget->alert_terkirim_pada = null;
        }
    }

    /**
     * Tulis kopi ai_usage_event di DB TENANT (konteks tenant). Idempoten via
     * unique idempotency_key; kegagalan TIDAK menggagalkan pencatatan ledger
     * (ledger central = sumber kebenaran, kopi tenant best-effort ter-log).
     */
    private function tulisUsageEvent(string $tenantId, array $data, string $idempotencyKey, float $kredit): void
    {
        $insert = static function () use ($data, $idempotencyKey, $kredit): void {
            AiUsageEvent::query()->create([
                'agent_key' => $data['agent_key'],
                'channel' => $data['channel'] ?? 'internal',
                'provider' => $data['provider'],
                'model' => $data['model'],
                'tier' => $data['tier'],
                'token_masuk' => $data['token_masuk'] ?? null,
                'token_keluar' => $data['token_keluar'] ?? null,
                'kredit' => $kredit,
                'degraded' => (bool) ($data['degraded'] ?? false),
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
            ]);
        };

        try {
            if (tenancy()->initialized && tenant()?->getTenantKey() === $tenantId) {
                $insert();
            } else {
                Tenant::query()->findOrFail($tenantId)->run($insert);
            }
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                Log::error('CreditMeter: GAGAL menulis ai_usage_event di DB tenant.', [
                    'tenant_id' => $tenantId,
                    'message' => $e->getMessage(),
                ]);
            }
            // Duplikat = no-op (idempoten).
        } catch (Throwable $e) {
            Log::error('CreditMeter: GAGAL menulis ai_usage_event di DB tenant.', [
                'tenant_id' => $tenantId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23505'
            || str_contains(strtolower($e->getMessage()), 'unique');
    }

    private function centralConnection(): string
    {
        return (string) config('tenancy.database.central_connection', 'pgsql');
    }
}
