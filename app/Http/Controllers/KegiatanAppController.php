<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kegiatan\Models\Kegiatan;

/**
 * PWA native "Kegiatan" — melengkapi alur linear menu staf HP: grup Kegiatan →
 * sub menu Kegiatan → LIST + FORM input versi PWA (owner 2026-07-06: mockup
 * di-breakdown dengan design system untuk melengkapi elemen yang belum ada,
 * bukan memblok akses ke panel).
 *
 * Data & aturan = SAMA dengan KegiatanResource panel (model/fillable/opsi
 * jenis-status identik) — hanya permukaan input baru, tanpa logika/skema baru.
 * Gate: view_kegiatan (lihat) / manage_kegiatan (tulis) — permission yang sama
 * dengan gerbang KegiatanResource::canAccess di panel.
 */
class KegiatanAppController extends Controller
{
    private const JENIS = ['umum', 'wajib', 'sunnah', 'pasaran'];

    private const STATUS = ['terjadwal', 'berlangsung', 'selesai', 'dibatalkan'];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $daftar = Kegiatan::query()
            ->with('penanggungJawab:id,name')
            ->when($q !== '', fn ($b) => $b->where('nama', 'ilike', "%{$q}%"))
            ->orderByDesc('mulai')
            ->limit(60)
            ->get()
            ->map(fn (Kegiatan $k): array => [
                'id' => $k->id,
                'nama' => $k->nama,
                'jenis' => $k->jenis,
                'status' => $k->status,
                'lokasi' => $k->lokasi,
                'mulai' => $k->mulai?->locale('id')->translatedFormat('d M Y H:i'),
                'selesai' => $k->selesai?->locale('id')->translatedFormat('d M Y H:i'),
                'pj' => $k->penanggungJawab?->name,
                'deskripsi' => $k->deskripsi,
            ])->values()->all();

        return Inertia::render('KegiatanApp/Index', [
            'daftar' => $daftar,
            'q' => $q,
            'bolehTulis' => $request->user()->can('manage_kegiatan'),
            'opsiJenis' => self::JENIS,
            'opsiStatus' => self::STATUS,
            'pjOptions' => User::query()->orderBy('name')->limit(200)->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage_kegiatan'), 403);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', 'in:'.implode(',', self::JENIS)],
            'mulai' => ['required', 'date'],
            'selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:'.implode(',', self::STATUS)],
            'penanggung_jawab_id' => ['nullable', 'integer', 'exists:users,id'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
        ]);

        Kegiatan::create($data + ['tenant_id' => tenant('id')]);

        return back()->with('success', 'Kegiatan tersimpan.');
    }

    /** Ubah status cepat dari list (terjadwal→berlangsung→selesai / batalkan). */
    public function ubahStatus(Request $request, Kegiatan $kegiatan): RedirectResponse
    {
        abort_unless($request->user()->can('manage_kegiatan'), 403);

        $data = $request->validate(['status' => ['required', 'in:'.implode(',', self::STATUS)]]);
        $kegiatan->update($data);

        return back()->with('success', "Status: {$data['status']}.");
    }
}
