<?php

namespace Modules\PPDB\Services;

use Illuminate\Support\Facades\DB;
use Modules\PPDB\Models\CalonSantri;
use RuntimeException;

class PpdbRegistrationNumberService
{
    public function generate(?int $year = null): string
    {
        $this->ensureTenantContext();

        $targetYear = $year ?? (int) now()->format('Y');
        $prefix = sprintf('PPDB-%d-', $targetYear);

        $lastNumber = DB::transaction(function () use ($prefix): ?string {
            return CalonSantri::query()
                ->where('nomor_pendaftaran', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('nomor_pendaftaran')
                ->value('nomor_pendaftaran');
        });

        $nextSequence = $this->extractNextSequence($lastNumber);

        return sprintf('%s%03d', $prefix, $nextSequence);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk generate nomor pendaftaran PPDB.');
        }
    }

    private function extractNextSequence(?string $lastNumber): int
    {
        if (! is_string($lastNumber) || $lastNumber === '') {
            return 1;
        }

        $parts = explode('-', $lastNumber);
        $lastSequence = (int) end($parts);

        return $lastSequence + 1;
    }
}
