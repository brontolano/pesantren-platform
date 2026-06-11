<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalGuru;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kesantrian\Models\JenisPelanggaran;
use Modules\Kesantrian\Models\Santri;

/**
 * WP-24 — Portal Guru Disiplin pages (Vue/Inertia).
 * Serve daftar pelanggaran, form catat, dan poin view.
 */
class PortalGuruDisiplinController extends Controller
{
    /**
     * GET /guru/disiplin/daftar — Daftar pelanggaran.
     */
    public function daftarPelanggaran(): Response
    {
        return Inertia::render('PortalGuru/Disiplin/DaftarPelanggaran', [
            'hari' => now()->translatedFormat('l'),
            'tanggal' => now()->translatedFormat('j F Y'),
            'istilah' => $this->resolveIstilah(),
        ]);
    }

    /**
     * GET /guru/disiplin/catat — Form catat pelanggaran.
     */
    public function formCatatPelanggaran(): Response
    {
        $santri = Santri::query()
            ->where('status', 'aktif')
            ->select(['id', 'nama', 'nis', 'kelas_id'])
            ->with('kelas:id,nama')
            ->orderBy('nama')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'nama' => $s->nama,
                'nis' => $s->nis,
                'kelas' => $s->kelas?->nama ?? '-',
            ]);

        $jenis = JenisPelanggaran::query()
            ->where('aktif', true)
            ->select(['id', 'nama', 'kategori', 'poin', 'sanksi_default'])
            ->orderBy('kategori')
            ->orderBy('nama')
            ->get();

        return Inertia::render('PortalGuru/Disiplin/FormCatatPelanggaran', [
            'hari' => now()->translatedFormat('l'),
            'tanggal' => now()->translatedFormat('j F Y'),
            'santriList' => $santri,
            'jenisList' => $jenis,
            'istilah' => $this->resolveIstilah(),
        ]);
    }

    /**
     * GET /guru/disiplin/poin — Poin terbaru.
     */
    public function poinView(): Response
    {
        return Inertia::render('PortalGuru/Disiplin/PoinView', [
            'hari' => now()->translatedFormat('l'),
            'tanggal' => now()->translatedFormat('j F Y'),
            'istilah' => $this->resolveIstilah(),
        ]);
    }

    // ===== Helpers =====

    private function resolveIstilah(): array
    {
        return [
            'halaqah' => 'halaqah',
            'santri' => 'santri',
            'kelas' => 'kelas',
        ];
    }
}
