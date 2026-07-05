<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Beasiswa;
use Modules\Keuangan\Models\Donasi;
use Modules\Keuangan\Models\ProgramDonasi;
use Modules\Keuangan\Services\DonasiService;
use Modules\Ledger\Models\SumberDana;

/**
 * Portal PWA "Baitul Maal" — Donasi/Program Donasi/Beasiswa sebelumnya TIDAK
 * PUNYA layar PWA sama sekali (bendahara_pusat/kepala_keuangan wajib buka
 * Filament admin). Data nyata via model Modules\Keuangan yang SUDAH dipakai
 * panel admin (DonasiResource/ProgramDonasiResource/BeasiswaResource) — tak
 * ada tabel/logika baru.
 *
 * Catatan skop: Donasi HANYA lahir dari halaman publik /donasi (donatur
 * self-submit) — DonasiResource::canCreate() selalu false. Jadi aksi tulis
 * bendahara di sini adalah konfirmasi/tolak (DonasiService), BUKAN buat
 * donasi baru — sama seperti admin.
 *
 * Gate: middleware('auth') + permission `view_donasi`/`manage_donasi` (role
 * kepala_keuangan/bendahara_pusat/keuangan sudah memilikinya).
 */
class BaitulMaalController extends Controller
{
    public function __construct(private readonly DonasiService $donasiService) {}

    private function nav(): array
    {
        $base = '/baitul-maal';
        $items = [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => $base, 'route' => 'beranda', 'icon' => 'home'],
            ['key' => 'donasi', 'label' => 'Donasi', 'href' => "{$base}/donasi", 'route' => 'donasi', 'icon' => 'heart'],
            ['key' => 'program', 'label' => 'Program', 'href' => "{$base}/program", 'route' => 'program', 'icon' => 'inbox'],
            ['key' => 'beasiswa', 'label' => 'Beasiswa', 'href' => "{$base}/beasiswa", 'route' => 'beasiswa', 'icon' => 'user'],
        ];

        return collect($items)
            ->map(fn (array $i): array => [
                'key' => $i['key'],
                'label' => $i['label'],
                'href' => $i['href'],
                'icon' => $i['icon'],
                'active' => request()->routeIs("tenant.baitul-maal.{$i['route']}"),
            ])
            ->all();
    }

    private function can(): array
    {
        return ['kelola' => (bool) Auth::user()?->can('manage_donasi')];
    }

    public function beranda(): Response
    {
        $tenantId = tenant('id');

        $menunggu = Donasi::where('tenant_id', $tenantId)->where('status', Donasi::STATUS_MENUNGGU)->count();
        $totalBulanIni = Donasi::where('tenant_id', $tenantId)
            ->where('status', Donasi::STATUS_DIKONFIRMASI)
            ->whereMonth('dikonfirmasi_at', now()->month)
            ->whereYear('dikonfirmasi_at', now()->year)
            ->sum('nominal');
        $programAktif = ProgramDonasi::where('tenant_id', $tenantId)->where('aktif', true)->count();
        $beasiswaAktif = Beasiswa::where('tenant_id', $tenantId)->where('aktif', true)->count();

        return Inertia::render('BaitulMaal/Beranda', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'userName' => Auth::user()?->name ?? 'Bendahara',
            'stats' => [
                ['key' => 'menunggu', 'label' => 'Donasi Menunggu', 'value' => (string) $menunggu, 'hint' => 'perlu konfirmasi', 'icon' => 'heart', 'tone' => 'amber'],
                ['key' => 'program', 'label' => 'Program Aktif', 'value' => (string) $programAktif, 'hint' => 'donasi berjalan', 'icon' => 'inbox', 'tone' => 'emerald'],
                ['key' => 'beasiswa', 'label' => 'Beasiswa Aktif', 'value' => (string) $beasiswaAktif, 'hint' => 'santri penerima', 'icon' => 'user', 'tone' => 'teal'],
            ],
            'totalDonasiBulanIni' => round((float) $totalBulanIni, 2),
        ]);
    }

    public function donasi(): Response
    {
        $tenantId = tenant('id');

        $donasi = Donasi::where('tenant_id', $tenantId)
            ->with('program:id,nama')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn (Donasi $d): array => [
                'id' => $d->id,
                'nama_donatur' => $d->nama_donatur,
                'kontak' => $d->kontak,
                'program' => $d->program?->nama ?? '-',
                'nominal' => (float) $d->nominal,
                'status' => $d->status,
                'bukti_url' => $d->bukti_path ? url('/_tenant-storage/'.$d->bukti_path) : null,
                'tanggal' => $d->created_at?->translatedFormat('d M Y H:i'),
            ]);

        return Inertia::render('BaitulMaal/Donasi', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'donasi' => $donasi->items(),
            'meta' => [
                'current_page' => $donasi->currentPage(),
                'last_page' => $donasi->lastPage(),
                'total' => $donasi->total(),
            ],
        ]);
    }

    public function donasiKonfirmasi(Donasi $donasi): RedirectResponse
    {
        if ($donasi->tenant_id !== tenant('id')) {
            abort(404);
        }

        $this->donasiService->konfirmasi($donasi, Auth::id());

        return back()->with('success', 'Donasi dikonfirmasi.');
    }

    public function donasiTolak(Donasi $donasi): RedirectResponse
    {
        if ($donasi->tenant_id !== tenant('id')) {
            abort(404);
        }

        $this->donasiService->tolak($donasi, Auth::id());

        return back()->with('success', 'Donasi ditolak.');
    }

    public function program(): Response
    {
        $tenantId = tenant('id');

        $program = ProgramDonasi::where('tenant_id', $tenantId)
            ->withCount('donasi')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ProgramDonasi $p): array => [
                'id' => $p->id,
                'nama' => $p->nama,
                'deskripsi' => $p->deskripsi,
                'target_nominal' => (float) $p->target_nominal,
                'terkumpul' => (float) $p->donasi()->where('status', Donasi::STATUS_DIKONFIRMASI)->sum('nominal'),
                'aktif' => $p->aktif,
                'jumlah_donasi' => $p->donasi_count,
            ]);

        return Inertia::render('BaitulMaal/Program', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'program' => $program,
            'sumberDana' => SumberDana::orderBy('kode_dana')->get(['id', 'kode_dana', 'nama_sumber']),
        ]);
    }

    public function programStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'target_nominal' => ['required', 'numeric', 'min:0'],
            'sumber_dana_id' => ['required', 'exists:sumber_dana,id'],
        ]);

        ProgramDonasi::create([...$data, 'tenant_id' => tenant('id'), 'aktif' => true]);

        return back()->with('success', 'Program donasi dibuat.');
    }

    public function programUpdate(Request $request, ProgramDonasi $program): RedirectResponse
    {
        if ($program->tenant_id !== tenant('id')) {
            abort(404);
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'target_nominal' => ['required', 'numeric', 'min:0'],
            'aktif' => ['required', 'boolean'],
        ]);

        $program->update($data);

        return back()->with('success', 'Program donasi diperbarui.');
    }

    public function beasiswa(Request $request): Response
    {
        $tenantId = tenant('id');
        $search = trim((string) $request->query('q', ''));

        $beasiswa = Beasiswa::where('tenant_id', $tenantId)
            ->with('santri:id,nama')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Beasiswa $b): array => [
                'id' => $b->id,
                'santri' => $b->santri?->nama ?? '-',
                'nominal_per_bulan' => (float) $b->nominal_per_bulan,
                'mulai' => optional($b->mulai)->translatedFormat('d M Y'),
                'selesai' => optional($b->selesai)->translatedFormat('d M Y'),
                'aktif' => $b->aktif,
                'keterangan' => $b->keterangan,
            ]);

        $query = Santri::where('tenant_id', $tenantId)->where('status', 'aktif')->with('kelas');
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(fn ($w) => $w->whereRaw('LOWER(nama) LIKE ?', [$term])->orWhereRaw('LOWER(nis) LIKE ?', [$term]));
        }
        $santriOptions = $query->orderBy('nama')->limit(50)->get()
            ->map(fn (Santri $s): array => ['value' => (string) $s->id, 'label' => trim($s->nama.' ('.($s->kelas?->nama_kelas ?? '-').')')])
            ->all();

        return Inertia::render('BaitulMaal/Beasiswa', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'beasiswa' => $beasiswa,
            'santriOptions' => $santriOptions,
            'sumberDana' => SumberDana::orderBy('kode_dana')->get(['id', 'kode_dana', 'nama_sumber']),
        ]);
    }

    public function beasiswaStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'santri_id' => ['required', 'integer', 'exists:santri,id'],
            'sumber_dana_id' => ['required', 'exists:sumber_dana,id'],
            'nominal_per_bulan' => ['required', 'numeric', 'min:0'],
            'mulai' => ['required', 'date'],
            'selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        Beasiswa::create([...$data, 'tenant_id' => tenant('id'), 'aktif' => true]);

        return back()->with('success', 'Beasiswa dicatat.');
    }

    public function beasiswaUpdate(Request $request, Beasiswa $beasiswa): RedirectResponse
    {
        if ($beasiswa->tenant_id !== tenant('id')) {
            abort(404);
        }

        $data = $request->validate([
            'aktif' => ['required', 'boolean'],
            'selesai' => ['nullable', 'date'],
        ]);

        $beasiswa->update($data);

        return back()->with('success', 'Beasiswa diperbarui.');
    }
}
