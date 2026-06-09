<?php

namespace Modules\Core\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Services\ApprovalService;

/**
 * Dipasang pada model yang butuh alur persetujuan (mis. Belanja, SlipGaji).
 * Modul consumer cukup `use Approvable` — TIDAK membuat tabel/logika approval sendiri.
 */
trait Approvable
{
    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'approvable');
    }

    /** Permintaan persetujuan yang masih menunggu (jika ada). */
    public function approvalMenunggu(): ?ApprovalRequest
    {
        return $this->approvalRequests()->where('status', 'menunggu')->latest('id')->first();
    }

    /** Apakah record ini WAJIB disetujui dulu (ada aturan wajib yang terpicu)? */
    public function butuhPersetujuan(): bool
    {
        return app(ApprovalService::class)->butuhPersetujuan($this);
    }

    /** Sudah ada keputusan disetujui? */
    public function sudahDisetujui(): bool
    {
        return $this->approvalRequests()->where('status', 'disetujui')->exists();
    }

    /** Ajukan persetujuan (no-op + return null bila tak ada aturan wajib terpicu). */
    public function ajukanPersetujuan(?int $userId = null): ?ApprovalRequest
    {
        return app(ApprovalService::class)->ajukan($this, $userId);
    }

    /**
     * Pagar untuk dipanggil consumer SEBELUM posting final:
     * lempar bila wajib-approval belum disetujui.
     */
    public function pastikanBolehLanjut(): void
    {
        app(ApprovalService::class)->pastikanBolehLanjut($this);
    }
}
