<?php

namespace Modules\Kesehatan\Services;

use Modules\Kesehatan\Models\RiwayatKesehatan;
use Modules\Kesehatan\Models\SesiKonseling;
use Modules\Kesantrian\Models\Santri;
use Illuminate\Support\Collection;

class KesehatanService
{
    // ── Riwayat Kesehatan ──────────────────────────────────────────────────

    public function catatKesehatan(Santri $santri, array $data): RiwayatKesehatan
    {
        return RiwayatKesehatan::create([
            'tenant_id' => $santri->tenant_id,
            'santri_id' => $santri->getKey(),
            'tanggal'   => $data['tanggal'] ?? now()->toDateString(),
            'keluhan'   => $data['keluhan'] ?? null,
            'diagnosa'  => $data['diagnosa'] ?? null,
            'tindakan'  => $data['tindakan'] ?? null,
            'kondisi'   => $data['kondisi'] ?? 'baik',
            'petugas'   => $data['petugas'] ?? null,
            'catatan'   => $data['catatan'] ?? null,
        ]);
    }

    public function getRiwayatSantri(Santri $santri): Collection
    {
        return RiwayatKesehatan::query()
            ->where('santri_id', $santri->getKey())
            ->orderByDesc('tanggal')
            ->get();
    }

    public function getSantriSakit(): Collection
    {
        return RiwayatKesehatan::query()
            ->where('kondisi', 'sakit')
            ->whereDate('tanggal', today())
            ->with('santri')
            ->get();
    }

    // ── Sesi Konseling ─────────────────────────────────────────────────────

    public function jadwalkanKonseling(Santri $santri, array $data): SesiKonseling
    {
        return SesiKonseling::create([
            'tenant_id'   => $santri->tenant_id,
            'santri_id'   => $santri->getKey(),
            'tanggal'     => $data['tanggal'],
            'topik'       => $data['topik'] ?? null,
            'deskripsi'   => $data['deskripsi'] ?? null,
            'status'      => 'terjadwal',
            'konselor_id' => $data['konselor_id'] ?? null,
        ]);
    }

    public function selesaikanKonseling(SesiKonseling $sesi, array $data): SesiKonseling
    {
        $sesi->update([
            'status'           => 'selesai',
            'rekomendasi'      => $data['rekomendasi'] ?? null,
            'catatan_lanjutan' => $data['catatan_lanjutan'] ?? null,
        ]);

        return $sesi->fresh();
    }

    public function getKonselingSantri(Santri $santri): Collection
    {
        return SesiKonseling::query()
            ->where('santri_id', $santri->getKey())
            ->with('konselor')
            ->orderByDesc('tanggal')
            ->get();
    }
}
