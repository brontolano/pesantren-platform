<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kesantrian\Models\Santri;

/**
 * PWA native "Izin Santri" — halaman detail shell /staf (owner 2026-07-06:
 * di HP TIDAK BOLEH ada menu yang membuka panel; semua native). Data & opsi
 * = IzinSantriResource panel. Gate baca view_kegiatan (pemegang manage_kegiatan
 * di seeder selalu punya view_kegiatan); tulis dicek manage_kegiatan.
 */
class PerizinanAppController extends Controller
{
    private const JENIS = ['pulang', 'sakit', 'keluar_kota', 'kunjungan'];

    private const STATUS = ['diajukan', 'disetujui', 'ditolak'];

    public function index(Request $request): Response
    {
        $daftar = IzinSantri::with('santri.kelas')
            ->orderByDesc('tanggal_mulai')
            ->limit(60)
            ->get()
            ->map(fn (IzinSantri $i): array => [
                'id' => $i->id,
                'santri' => $i->santri?->nama ?? '—',
                'kelas' => $i->santri?->kelas?->nama_kelas ?? '—',
                'jenis' => $i->jenis_izin,
                'alasan' => $i->alasan,
                'mulai' => $i->tanggal_mulai?->translatedFormat('d M Y'),
                'selesai' => $i->tanggal_selesai?->translatedFormat('d M Y'),
                'status' => $i->status,
                'sudah_kembali' => (bool) $i->sudah_kembali,
            ])->values()->all();

        return Inertia::render('PerizinanApp/Index', [
            'daftar' => $daftar,
            'bolehTulis' => $request->user()->can('manage_kegiatan'),
            'opsiJenis' => self::JENIS,
            'santriOptions' => Santri::query()->where('status', 'aktif')->orderBy('nama')
                ->limit(300)->get(['id', 'nis', 'nama'])
                ->map(fn ($s) => ['id' => $s->id, 'label' => "{$s->nama} ({$s->nis})"])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage_kegiatan'), 403);

        $data = $request->validate([
            'santri_id' => ['required', 'integer', 'exists:santri,id'],
            'jenis_izin' => ['required', 'in:'.implode(',', self::JENIS)],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'alasan' => ['required', 'string', 'max:500'],
        ]);

        IzinSantri::create($data + [
            'tenant_id' => tenant('id'),
            'status' => 'disetujui',
            'disetujui_oleh' => $request->user()->id,
        ]);

        return back()->with('success', 'Izin tercatat.');
    }

    /** Tandai santri sudah kembali ke pesantren. */
    public function kembali(Request $request, IzinSantri $izin): RedirectResponse
    {
        abort_unless($request->user()->can('manage_kegiatan'), 403);

        $izin->update(['sudah_kembali' => true, 'tanggal_kembali_aktual' => now()]);

        return back()->with('success', 'Ditandai sudah kembali.');
    }
}
