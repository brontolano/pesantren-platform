<?php

namespace Modules\AI\Services\Audit;

use Illuminate\Support\Facades\Log;
use Modules\AI\Models\AiAuditLog;
use Throwable;

/**
 * T-A003 — pencatat audit AI. Satu baris ai_audit_log per panggilan gateway
 * (sukses / degradasi / gagal / ditolak_policy).
 *
 * Kontrak penting: penulisan audit TIDAK BOLEH menggagalkan permintaan utama.
 * record() menelan exception-nya sendiri dan mencatatnya keras ke log Laravel
 * (recordOrLog) — pemanggil tetap mendapat jawabannya.
 *
 * TODO(T-A002): alarm anomali biaya > 3× baseline tenant DITUNDA ke T-A002 —
 * butuh ai_usage_event sebagai sumber baseline harian. Saat metering tersedia,
 * pasang pembanding biaya_kredit harian vs baseline di sini / di UsageRecorder
 * dan kirim notifikasi operator via kanal Notifikasi eksisting (Blueprint §9).
 */
class AuditRecorder
{
    /**
     * Tulis satu baris audit. Melempar exception bila gagal — pemanggil yang
     * berada di jalur permintaan utama WAJIB memakai recordOrLog().
     *
     * @param  array<string, mixed>  $data
     */
    public function record(array $data): AiAuditLog
    {
        $data['created_at'] ??= now();

        return AiAuditLog::create($data);
    }

    /**
     * Varian aman-jalur-utama: kegagalan tulis audit dicatat keras ke log
     * Laravel, TIDAK dilempar ulang. null = gagal tercatat.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordOrLog(array $data): ?AiAuditLog
    {
        try {
            return $this->record($data);
        } catch (Throwable $e) {
            Log::error('AuditRecorder: GAGAL menulis ai_audit_log — permintaan utama tetap dilanjutkan.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'agent_key' => $data['agent_key'] ?? null,
                'status' => $data['status'] ?? null,
            ]);

            return null;
        }
    }
}
