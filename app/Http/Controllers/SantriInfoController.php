<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kesantrian\Models\Santri;

/**
 * PWA native "Data Santri" BACA-SAJA — untuk pemegang view_santri TANPA
 * manage_santri (guru/akuntan/konselor/UKS/kasir/…): dulu item Santri membuka
 * panel di HP. Pemegang manage_santri tetap diarahkan ke /tu/santri (CRUD).
 */
class SantriInfoController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $daftar = Santri::query()
            ->with('kelas:id,nama_kelas')
            ->where('status', 'aktif')
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w
                ->where('nama', 'ilike', "%{$q}%")
                ->orWhere('nis', 'ilike', "%{$q}%")))
            ->orderBy('nama')
            ->limit(100)
            ->get(['id', 'nis', 'nama', 'kelas_id', 'jenis_kelamin'])
            ->map(fn (Santri $s): array => [
                'id' => $s->id,
                'nis' => $s->nis,
                'nama' => $s->nama,
                'kelas' => $s->kelas?->nama_kelas,
                'jk' => $s->jenis_kelamin,
            ])->values()->all();

        return Inertia::render('SantriInfo/Index', ['daftar' => $daftar, 'q' => $q]);
    }
}
