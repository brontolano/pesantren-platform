<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesehatan\Models\KunjunganUks;
use Modules\Kesehatan\Models\Obat;
use Modules\Kesehatan\Services\KesehatanService;
use Modules\Kesehatan\Services\ObatService;

/**
 * Portal PWA "Kesehatan UKS" (Vue/Inertia) — persona pengelola_uks, DATA NYATA.
 *
 * Menggantikan UksDemoController: query nyata ke tabel kunjungan_uks, obat,
 * riwayat_kesehatan milik Modules\Kesehatan (dibangun di WP-27/WP-28), bukan
 * tabel baru — modul UKS backend SUDAH ADA & lengkap (KesehatanService,
 * KunjunganUksObserver auto-sync presensi sakit, ObatService stok-terkunci).
 *
 * Gate: middleware('auth') + permission `view_kesehatan` (role pengelola_uks
 * sudah diberi permission ini di RolesAndPermissionsSeeder).
 */
class UksController extends Controller
{
    public function __construct(
        private readonly KesehatanService $kesehatan,
        private readonly ObatService $obatService,
    ) {}

    /** Kontrak nav bottom-bar, identik dgn UksDemoController. */
    private function nav(): array
    {
        $base = '/uks';
        $items = [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => '/staf', 'route' => 'beranda', 'icon' => 'home'],
            ['key' => 'kunjungan', 'label' => 'Kunjungan', 'href' => "{$base}/kunjungan", 'route' => 'kunjungan', 'icon' => 'check-circle'],
            ['key' => 'obat', 'label' => 'Obat', 'href' => "{$base}/obat", 'route' => 'obat', 'icon' => 'receipt'],
            ['key' => 'rujukan', 'label' => 'Rujukan', 'href' => "{$base}/rujukan", 'route' => 'rujukan', 'icon' => 'inbox'],
            ['key' => 'riwayat', 'label' => 'Riwayat', 'href' => "{$base}/riwayat", 'route' => 'riwayat', 'icon' => 'heart'],
        ];

        return collect($items)
            ->map(fn (array $i): array => [
                'key' => $i['key'],
                'label' => $i['label'],
                'href' => $i['href'],
                'icon' => $i['icon'],
                'active' => request()->routeIs("tenant.uks.{$i['route']}"),
            ])
            ->all();
    }

    private function can(): array
    {
        $user = Auth::user();

        return [
            'catat_kunjungan' => (bool) $user?->can('manage_kesehatan'),
            'kelola_obat' => (bool) $user?->can('manage_kesehatan'),
            'kelola_rujukan' => (bool) $user?->can('manage_kesehatan'),
        ];
    }

    /** Label ringkas "Nama (Kelas)" konsisten dgn kartu Vue existing. */
    private function labelSantri(Santri $santri): array
    {
        return [
            'nama' => $santri->nama,
            'kelas' => $santri->kelas?->nama_kelas ?? '-',
        ];
    }

    public function beranda(): Response
    {
        $tenantId = tenant('id');

        $kunjunganHariIni = KunjunganUks::where('tenant_id', $tenantId)
            ->whereDate('tanggal', now()->toDateString())
            ->count();

        $istirahat = KunjunganUks::where('tenant_id', $tenantId)
            ->where('status', 'istirahat')
            ->whereDate('tanggal', now()->toDateString())
            ->whereNull('jam_keluar')
            ->count();

        $rujukanTerbuka = KunjunganUks::where('tenant_id', $tenantId)
            ->where('status', 'rujuk')
            ->whereNull('jam_keluar')
            ->count();

        $obatMenipis = Obat::where('tenant_id', $tenantId)->menipis()->count();

        $terbaru = KunjunganUks::where('tenant_id', $tenantId)
            ->whereDate('tanggal', now()->toDateString())
            ->with(['santri.kelas'])
            ->orderByDesc('jam_masuk')
            ->limit(10)
            ->get()
            ->map(function (KunjunganUks $k): array {
                return [
                    'id' => $k->id,
                    'nama' => $k->santri?->nama ?? '-',
                    'kelas' => $k->santri?->kelas?->nama_kelas ?? '-',
                    'keluhan' => $k->keluhan ?? '-',
                    'status' => $k->status,
                    'jam' => optional($k->jam_masuk)->format('H:i') ?? '-',
                ];
            });

        return Inertia::render('Uks/Beranda', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'userName' => Auth::user()?->name ?? 'Petugas UKS',
            'stats' => [
                ['key' => 'kunjungan', 'label' => 'Kunjungan Hari Ini', 'value' => (string) $kunjunganHariIni, 'hint' => "{$istirahat} sedang ditangani", 'icon' => 'check-circle', 'tone' => 'emerald'],
                ['key' => 'istirahat', 'label' => 'Santri Istirahat', 'value' => (string) $istirahat, 'hint' => 'di kamar pemulihan', 'icon' => 'heart', 'tone' => 'amber'],
                ['key' => 'rujukan', 'label' => 'Rujukan Terbuka', 'value' => (string) $rujukanTerbuka, 'hint' => 'menunggu jemputan', 'icon' => 'inbox', 'tone' => 'rose'],
                ['key' => 'obat', 'label' => 'Obat Menipis', 'value' => (string) $obatMenipis, 'hint' => 'stok ≤ ambang', 'icon' => 'receipt', 'tone' => 'blue'],
            ],
            'kunjunganTerbaru' => $terbaru,
        ]);
    }

    public function kunjungan(): Response
    {
        $tenantId = tenant('id');

        $kunjungan = KunjunganUks::where('tenant_id', $tenantId)
            ->whereDate('tanggal', now()->toDateString())
            ->with(['santri.kelas'])
            ->orderByDesc('jam_masuk')
            ->paginate(20)
            ->through(fn (KunjunganUks $k): array => [
                'id' => $k->id,
                'nama' => $k->santri?->nama ?? '-',
                'kelas' => $k->santri?->kelas?->nama_kelas ?? '-',
                'keluhan' => $k->keluhan ?? '-',
                'tindakan' => $k->tindakan ?? '-',
                'status' => $k->status,
                'jam' => optional($k->jam_masuk)->format('H:i') ?? '-',
                'ditutup' => $k->jam_keluar !== null,
            ]);

        return Inertia::render('Uks/Kunjungan', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'kunjungan' => $kunjungan->items(),
            'meta' => [
                'current_page' => $kunjungan->currentPage(),
                'last_page' => $kunjungan->lastPage(),
                'total' => $kunjungan->total(),
            ],
        ]);
    }

    public function tutupKunjungan(int $kunjungan): RedirectResponse
    {
        $tenantId = tenant('id');
        $model = KunjunganUks::where('tenant_id', $tenantId)->findOrFail($kunjungan);

        $this->kesehatan->tutupKunjungan($model);

        return back()->with('success', 'Kunjungan ditutup.');
    }

    public function catatKunjunganForm(Request $request): Response
    {
        $tenantId = tenant('id');
        $search = trim((string) $request->query('q', ''));

        $query = Santri::where('tenant_id', $tenantId)
            ->where('status', 'aktif')
            ->with('kelas');

        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($w) use ($term): void {
                $w->whereRaw('LOWER(nama) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(nis) LIKE ?', [$term]);
            });
        }

        $santriOptions = $query->orderBy('nama')
            ->limit(50)
            ->get()
            ->map(fn (Santri $s): array => [
                'value' => (string) $s->id,
                'label' => trim($s->nama.' ('.($s->kelas?->nama_kelas ?? '-').')'),
            ])
            ->all();

        return Inertia::render('Uks/CatatKunjungan', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'santriOptions' => $santriOptions,
        ]);
    }

    public function catatKunjunganStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'santri_id' => ['required', 'integer', 'exists:santri,id'],
            'keluhan' => ['required', 'string', 'max:1000'],
            'tindakan' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:pulang,istirahat,rujuk'],
        ]);

        $santri = Santri::where('tenant_id', tenant('id'))->findOrFail($data['santri_id']);

        $this->kesehatan->catatKunjungan(
            $santri,
            now()->toDateString(),
            $data['status'],
            [
                'keluhan' => $data['keluhan'],
                'tindakan' => $data['tindakan'] ?? null,
                'pencatat_id' => Auth::id(),
            ]
        );

        return redirect()->route('tenant.uks.kunjungan')->with('success', 'Kunjungan berhasil dicatat.');
    }

    public function obat(): Response
    {
        $tenantId = tenant('id');

        $obat = Obat::where('tenant_id', $tenantId)
            ->where('aktif', true)
            ->orderBy('nama')
            ->get()
            ->map(fn (Obat $o): array => [
                'id' => $o->id,
                'nama' => $o->nama,
                'satuan' => $o->satuan,
                'stok' => $o->stok,
                'ambang_menipis' => $o->ambang_menipis,
            ]);

        return Inertia::render('Uks/Obat', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'obat' => $obat,
        ]);
    }

    /**
     * Catat pemakaian obat (stok keluar) — lewat ObatService::keluar() yang
     * sudah lockForUpdate + tolak stok minus. Dipakai saat menangani santri
     * sakit (mis. beri obat saat kunjungan), tanpa harus lewat panel admin.
     */
    public function obatPakaiStore(Request $request, Obat $obat): RedirectResponse
    {
        if ($obat->tenant_id !== tenant('id')) {
            abort(404);
        }

        $data = $request->validate([
            'jumlah' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->obatService->keluar($obat, $data['jumlah'], null, [
                'keterangan' => $data['keterangan'] ?? null,
                'user_id' => Auth::id(),
            ]);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['jumlah' => $e->getMessage()]);
        }

        return back()->with('success', "Pemakaian {$obat->nama} dicatat.");
    }

    public function rujukan(): Response
    {
        $tenantId = tenant('id');

        $rujukan = KunjunganUks::where('tenant_id', $tenantId)
            ->where('status', 'rujuk')
            ->with(['santri.kelas'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (KunjunganUks $k): array => [
                'id' => $k->id,
                'nama' => $k->santri?->nama ?? '-',
                'kelas' => $k->santri?->kelas?->nama_kelas ?? '-',
                'keluhan' => $k->keluhan ?? '-',
                'tujuan' => $k->rujukan_ke ?? '-',
                'biaya' => (float) ($k->rujukan_biaya ?? 0),
                'status' => $k->jam_keluar ? 'terverifikasi' : 'menunggu',
                'tanggal' => optional($k->tanggal)->translatedFormat('d M Y') ?? '-',
            ]);

        return Inertia::render('Uks/Rujukan', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'rujukan' => $rujukan,
        ]);
    }

    /**
     * Isi/perbarui tujuan & estimasi biaya rujukan. Menutup rujukan (jam
     * keluar) sudah difasilitasi endpoint kunjungan.tutup yang ada.
     */
    public function rujukanUpdateStore(Request $request, KunjunganUks $kunjungan): RedirectResponse
    {
        if ($kunjungan->tenant_id !== tenant('id') || $kunjungan->status !== 'rujuk') {
            abort(404);
        }

        $data = $request->validate([
            'rujukan_ke' => ['required', 'string', 'max:255'],
            'rujukan_biaya' => ['nullable', 'numeric', 'min:0'],
        ]);

        $kunjungan->update($data);

        return back()->with('success', 'Detail rujukan diperbarui.');
    }

    public function riwayat(): Response
    {
        $tenantId = tenant('id');

        $riwayat = KunjunganUks::where('tenant_id', $tenantId)
            ->with(['santri.kelas'])
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_masuk')
            ->limit(100)
            ->get()
            ->map(fn (KunjunganUks $k): array => [
                'id' => $k->id,
                'nama' => $k->santri?->nama ?? '-',
                'kelas' => $k->santri?->kelas?->nama_kelas ?? '-',
                'diagnosa' => $k->keluhan ?? '-',
                'tindakan' => $k->tindakan ?? '-',
                'status' => $k->status,
                'tanggal' => optional($k->tanggal)->translatedFormat('d M Y') ?? '-',
                'jam' => optional($k->jam_masuk)->format('H:i') ?? '-',
            ]);

        return Inertia::render('Uks/Riwayat', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'riwayat' => $riwayat,
        ]);
    }
}
