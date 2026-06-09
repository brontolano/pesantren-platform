<?php

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Events\ApprovalDiputuskan;
use Modules\Core\Exceptions\PersetujuanBelumLengkapException;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\ApprovalRule;
use RuntimeException;

/**
 * F1 — Mesin persetujuan lintas-modul. Satu-satunya tempat logika approval;
 * modul consumer memanggil lewat trait Approvable (tak menyalin logika).
 */
class ApprovalService
{
    /** Aturan WAJIB pertama (urutan terkecil) yang terpicu untuk $model, atau null. */
    public function aturanTerpicu(Model $model): ?ApprovalRule
    {
        return ApprovalRule::query()
            ->aktif()
            ->untukTipe($model::class)
            ->where('mode', 'wajib')
            ->orderBy('urutan')
            ->get()
            ->first(fn (ApprovalRule $r) => $r->terpicu($model));
    }

    public function butuhPersetujuan(Model $model): bool
    {
        return $this->aturanTerpicu($model) !== null;
    }

    /**
     * Ajukan persetujuan. Idempoten: jika sudah ada request 'menunggu', kembalikan
     * itu. Jika tak ada aturan wajib terpicu → null (tak perlu approval).
     */
    public function ajukan(Model $model, ?int $userId = null): ?ApprovalRequest
    {
        $this->ensureTenantContext();

        $aturan = $this->aturanTerpicu($model);
        if ($aturan === null) {
            return null;
        }

        $existing = $model->approvalRequests()->where('status', 'menunggu')->latest('id')->first();
        if ($existing !== null) {
            return $existing;
        }

        return ApprovalRequest::create([
            'approvable_type' => $model::class,
            'approvable_id' => $model->getKey(),
            'status' => 'menunggu',
            'current_step' => $aturan->urutan,
            'approver_role' => $aturan->approver_role,
            'diminta_oleh' => $userId,
        ]);
    }

    public function setujui(ApprovalRequest $request, int $userId): ApprovalRequest
    {
        return $this->putuskan($request, $userId, true, null);
    }

    public function tolak(ApprovalRequest $request, int $userId, string $alasan): ApprovalRequest
    {
        return $this->putuskan($request, $userId, false, $alasan);
    }

    private function putuskan(ApprovalRequest $request, int $userId, bool $disetujui, ?string $alasan): ApprovalRequest
    {
        if (! $request->isMenunggu()) {
            throw new RuntimeException('Permintaan persetujuan sudah diputuskan.');
        }

        $request->update([
            'status' => $disetujui ? 'disetujui' : 'ditolak',
            'diputus_oleh' => $userId,
            'diputus_pada' => now(),
            'alasan' => $alasan,
        ]);

        ApprovalDiputuskan::dispatch($request->fresh(), $disetujui);

        return $request->fresh();
    }

    /** Lempar bila model wajib disetujui tapi belum ada keputusan 'disetujui'. */
    public function pastikanBolehLanjut(Model $model): void
    {
        if (! $this->butuhPersetujuan($model)) {
            return;
        }
        if ($model->approvalRequests()->where('status', 'disetujui')->exists()) {
            return;
        }

        throw new PersetujuanBelumLengkapException(
            'Transaksi ini membutuhkan persetujuan dan belum disetujui.'
        );
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk approval.');
        }
    }
}
