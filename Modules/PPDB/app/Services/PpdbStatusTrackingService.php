<?php

namespace Modules\PPDB\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\PPDB\Models\CalonSantri;
use RuntimeException;

class PpdbStatusTrackingService
{
    public function issueTrackingLink(CalonSantri|int $calonSantri): array
    {
        $this->ensureTenantContext();

        $calon = $calonSantri instanceof CalonSantri
            ? $calonSantri
            : CalonSantri::query()->findOrFail($calonSantri);

        $token = DB::transaction(function () use ($calon): string {
            $locked = CalonSantri::query()->lockForUpdate()->findOrFail($calon->id);

            if (is_string($locked->tracking_token) && $locked->tracking_token !== '') {
                return $locked->tracking_token;
            }

            $token = Str::random(40);

            $locked->update([
                'tracking_token' => $token,
                'tracking_token_generated_at' => now(),
            ]);

            return $token;
        });

        return [
            'tracking_token' => $token,
            'tracking_path' => '/ppdb/tracking/'.$token,
            'tracking_url' => url('/ppdb/tracking/'.$token),
        ];
    }

    public function resolveCandidate(string $token): CalonSantri
    {
        $this->ensureTenantContext();

        return CalonSantri::query()
            ->where('tracking_token', $token)
            ->firstOrFail();
    }

    public function getStatusSnapshot(string $token): array
    {
        $calon = $this->resolveCandidate($token);

        return [
            'tracking_token' => $calon->tracking_token,
            'nomor_pendaftaran' => $calon->nomor_pendaftaran,
            'nama' => $calon->nama,
            'status_pendaftaran' => $calon->status_pendaftaran,
            'dokumen_terkirim' => $calon->dokumen()->count(),
            'hasil_ujian_tercatat' => $calon->hasilUjian()->count(),
            'tracking_path' => '/ppdb/tracking/'.$calon->tracking_token,
        ];
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk tracking status PPDB.');
        }
    }
}
