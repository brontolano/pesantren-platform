<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Kesantrian\Models\Kelas;

/**
 * PWA native "Akademik" (alur linear menu staf HP): Kelas (view + isi kelas)
 * dan Rekap Presensi Santri (view per tanggal). Data & gerbang izin = SAMA
 * dengan KelasResource/PresensiSantriResource panel (view_akademik dkk) —
 * permukaan baca versi PWA, tanpa logika baru. Input presensi tetap di
 * PWA Guru (/guru/presensi — default hadir), bukan diduplikasi di sini.
 */
class AkademikAppController extends Controller
{
    public function kelas(): Response
    {
        $daftar = Kelas::query()
            ->withCount('santri')
            ->with('waliKelas:id,name')
            ->orderBy('nama_kelas')
            ->limit(100)
            ->get()
            ->map(fn (Kelas $k): array => [
                'id' => $k->id,
                'nama' => $k->nama_kelas,
                'tingkat' => $k->tingkat,
                'jurusan' => $k->jurusan,
                'kapasitas' => $k->capacity,
                'jumlah' => $k->santri_count,
                'wali' => $k->waliKelas?->name,
            ])->values()->all();

        return Inertia::render('AkademikApp/Kelas', ['daftar' => $daftar]);
    }

    /** Isi kelas (detail): daftar santri satu kelas. */
    public function kelasDetail(Kelas $kelas): Response
    {
        return Inertia::render('AkademikApp/KelasDetail', [
            'kelas' => [
                'id' => $kelas->id,
                'nama' => $kelas->nama_kelas,
                'tingkat' => $kelas->tingkat,
                'wali' => $kelas->waliKelas?->name,
            ],
            'santri' => $kelas->santri()->orderBy('nama')->get(['id', 'nis', 'nama', 'status'])
                ->map(fn ($s) => ['id' => $s->id, 'nis' => $s->nis, 'nama' => $s->nama, 'status' => $s->status])
                ->values()->all(),
        ]);
    }

    /** Rekap presensi santri per tanggal (default hari ini). */
    public function presensi(Request $request): Response
    {
        $tanggal = (string) $request->query('tanggal', now()->toDateString());

        $rows = PresensiSantri::query()
            ->with(['santri:id,nis,nama', 'kelas:id,nama_kelas'])
            ->whereDate('tanggal', $tanggal)
            ->orderBy('kelas_id')
            ->limit(500)
            ->get();

        $ringkas = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
        foreach ($rows as $r) {
            if (isset($ringkas[$r->status])) {
                $ringkas[$r->status]++;
            }
        }

        return Inertia::render('AkademikApp/Presensi', [
            'tanggal' => $tanggal,
            'ringkas' => $ringkas,
            'rows' => $rows->map(fn (PresensiSantri $p): array => [
                'id' => $p->id,
                'nis' => $p->santri?->nis,
                'nama' => $p->santri?->nama,
                'kelas' => $p->kelas?->nama_kelas,
                'status' => $p->status,
            ])->values()->all(),
        ]);
    }
}
