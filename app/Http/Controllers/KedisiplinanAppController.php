<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kesantrian\Models\JenisPelanggaran;

/**
 * PWA native "Jenis Ta'zir" (master kedisiplinan) — halaman detail shell /staf.
 * Data & opsi = JenisPelanggaranResource panel (KATEGORI_OPTIONS identik).
 * Gate baca view_kedisiplinan; tulis manage_kedisiplinan.
 */
class KedisiplinanAppController extends Controller
{
    private const KATEGORI = ['ringan', 'sedang', 'berat', 'prestasi'];

    public function jenisTazir(Request $request): Response
    {
        $daftar = JenisPelanggaran::query()
            ->orderBy('kategori')->orderBy('nama')
            ->limit(200)
            ->get()
            ->map(fn (JenisPelanggaran $j): array => [
                'id' => $j->id,
                'nama' => $j->nama,
                'kategori' => $j->kategori,
                'poin' => $j->poin,
                'sanksi' => $j->sanksi_default,
                'aktif' => (bool) $j->aktif,
            ])->values()->all();

        return Inertia::render('KedisiplinanApp/JenisTazir', [
            'daftar' => $daftar,
            'bolehTulis' => $request->user()->can('manage_kedisiplinan'),
            'opsiKategori' => self::KATEGORI,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage_kedisiplinan'), 403);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'kategori' => ['required', 'in:'.implode(',', self::KATEGORI)],
            // Prestasi = poin negatif; kategori lain positif (aturan sama panel).
            'poin' => ['required', 'integer', 'min:-1000', 'max:1000'],
            'sanksi_default' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['kategori'] === 'prestasi' ? $data['poin'] > 0 : $data['poin'] < 0) {
            return back()->withErrors(['poin' => 'Prestasi berpoin negatif; kategori lain berpoin positif.']);
        }

        JenisPelanggaran::create($data + ['tenant_id' => tenant('id'), 'aktif' => true]);

        return back()->with('success', 'Jenis ta\'zir tersimpan.');
    }
}
