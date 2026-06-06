<?php

namespace Modules\PPDB\Services;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\PPDB\Models\CalonSantri;
use RuntimeException;

class PpdbAdmissionDecisionService
{
    private const ALLOWED_STATUSES = [
        'lulus',
        'cadangan',
        'ditolak',
    ];

    public function decide(CalonSantri|int $calonSantri, array $payload): CalonSantri
    {
        $this->ensureTenantContext();

        $candidate = $calonSantri instanceof CalonSantri
            ? $calonSantri
            : CalonSantri::query()->findOrFail($calonSantri);

        $status = $this->normalizeStatus((string) ($payload['status_pendaftaran'] ?? ''));

        if (in_array($status, ['lulus', 'cadangan'], true) && ! $candidate->hasilUjian()->exists()) {
            throw new InvalidArgumentException('Keputusan lulus atau cadangan membutuhkan minimal satu hasil ujian.');
        }

        $candidate->forceFill([
            'status_pendaftaran' => $status,
            'catatan_keputusan' => $this->normalizeDecisionNote($payload['catatan_keputusan'] ?? null),
            'tanggal_keputusan' => $payload['tanggal_keputusan'] ?? Carbon::now(),
        ])->save();

        // F3/ANN-2: kabari peserta via WA saat keputusan seleksi dipublikasikan
        // (fire-and-forget; tak menggagalkan keputusan bila WA gagal).
        if ($candidate->no_hp_wali) {
            app(PpdbWhatsappNotifier::class)->sendPengumuman(
                (string) $candidate->no_hp_wali,
                (string) $candidate->nama,
                (string) $candidate->nomor_pendaftaran,
                $status,
            );
        }

        return $candidate->fresh();
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk keputusan penerimaan PPDB.');
        }
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = trim(strtolower(str_replace(['-', ' '], '_', $status)));

        if (! in_array($normalized, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException('Status keputusan PPDB harus salah satu dari: lulus, cadangan, atau ditolak.');
        }

        return $normalized;
    }

    private function normalizeDecisionNote(mixed $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $normalized = trim((string) $note);

        return $normalized === '' ? null : $normalized;
    }
}
