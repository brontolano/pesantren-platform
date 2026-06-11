<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalGuru;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalGuru\Concerns\ResolvesPortalGuruContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Akademik\Models\JadwalPelajaran;

/**
 * F1-PWA — Layar "Hari Ini": kartu sesi mengajar dari JadwalPelajaran
 * (pelajaran.guru_pengampu_id = saya, hari ini, urut jam) + halaqah binaan
 * (musyrif_id = saya). Tiap kartu menautkan ke presensi / setoran.
 */
class PortalGuruBerandaController extends Controller
{
    use ResolvesPortalGuruContext;

    public function __invoke(Request $request): Response
    {
        $guru = $this->currentGuru($request);
        $hari = self::hariIni();

        $sesi = JadwalPelajaran::query()
            ->with(['kelas', 'pelajaran'])
            ->whereHas('pelajaran', fn ($q) => $q->where('guru_pengampu_id', $guru->id))
            ->where('hari', $hari)
            ->orderBy('jam_mulai')
            ->get()
            ->map(fn (JadwalPelajaran $jadwal): array => [
                'id' => $jadwal->id,
                'pelajaran' => $jadwal->pelajaran?->nama,
                'kelas' => $jadwal->kelas?->nama_kelas,
                'jam_mulai' => $jadwal->jam_mulai,
                'jam_selesai' => $jadwal->jam_selesai,
                'ruangan' => $jadwal->ruangan,
                'presensi_url' => "/guru/presensi/{$jadwal->id}",
            ])
            ->values();

        $halaqah = $this->halaqahKu($guru)
            ->map(fn ($h): array => [
                'id' => $h->id,
                'nama' => $h->nama,
                'jenis' => $h->jenis,
                'jumlah_santri' => $h->santriAktif()->count(),
                'setoran_url' => "/guru/setoran/{$h->id}",
            ])
            ->values();

        return Inertia::render('PortalGuru/Beranda', [
            ...$this->sharedProps($request),
            'hari' => $hari,
            'tanggal' => now()->toDateString(),
            'sesi' => $sesi,
            'halaqah' => $halaqah,
        ]);
    }
}
