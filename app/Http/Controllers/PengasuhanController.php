<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Asrama\Models\PenempatanAsrama;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kesantrian\Models\JenisPelanggaran;
use Modules\Kesantrian\Models\PelanggaranSantri;
use Modules\Kesantrian\Models\PresensiAsrama;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\DisiplinService;
use Modules\Kesantrian\Services\PresensiAsramaService;
use Throwable;

/**
 * PWA "Pengasuhan/Asrama" — persona Musyrif/pengelola asrama. Menggantikan
 * PengasuhanDemoController: data nyata via Modules\Asrama (kamar/penempatan
 * — sudah dipakai KamarAsramaResource/PenempatanAsramaResource di panel
 * admin) dan Modules\Kesantrian PresensiAsramaService/DisiplinService
 * (dipakai PresensiAsramaResource/PelanggaranSantriResource admin) — tidak
 * ada logika baru, murni disambungkan ke layar PWA.
 *
 * Izin (IzinSantri, modul Kegiatan) ditampilkan READ-ONLY: modul Kegiatan
 * digerbangi permission `manage_kegiatan` yang TIDAK dimiliki role
 * `pengelola_asrama` (RolesAndPermissionsSeeder) — menampilkan daftar tanpa
 * aksi setuju/tolak menghindari menambah permission baru di luar scope.
 *
 * Gate: middleware('auth') + can:manage_asrama — permission yang SUDAH
 * dimiliki role `pengelola_asrama` dan dipakai KamarAsramaResource/
 * PenempatanAsramaResource::canCreate() di panel admin (view_asrama dipakai
 * utk canAccess baca; manage_asrama dipilih di sini karena musyrif JUGA
 * mencatat presensi/disiplin, bukan cuma melihat).
 */
class PengasuhanController extends Controller
{
    public function __construct(
        private readonly PresensiAsramaService $presensiService,
        private readonly DisiplinService $disiplinService,
    ) {}

    private function nav(): array
    {
        $base = '/pengasuhan';

        $items = [
            ['key' => 'hariini', 'label' => 'Hari Ini', 'icon' => 'calendar', 'href' => $base, 'route' => 'beranda'],
            ['key' => 'presensi', 'label' => 'Presensi', 'icon' => 'check-circle', 'href' => "{$base}/presensi", 'route' => 'presensi'],
            ['key' => 'disiplin', 'label' => 'Disiplin', 'icon' => 'chart', 'href' => "{$base}/disiplin", 'route' => 'disiplin'],
            ['key' => 'izin', 'label' => 'Izin', 'icon' => 'document', 'href' => "{$base}/izin", 'route' => 'izin'],
        ];

        return array_map(fn (array $i): array => [
            'key' => $i['key'],
            'label' => $i['label'],
            'icon' => $i['icon'],
            'href' => $i['href'],
            'active' => request()->routeIs("tenant.pengasuhan.{$i['route']}"),
        ], $items);
    }

    private function userName(): string
    {
        return Auth::user()?->name ?: 'Ustadz Musyrif';
    }

    public function beranda(): Response
    {
        $tenantId = tenant('id');

        $santriBinaan = PenempatanAsrama::where('tenant_id', $tenantId)->where('is_current', true)->count();

        $izinAktif = IzinSantri::where('status', 'disetujui')->where('sudah_kembali', false)->count();

        $pelanggaranPending = PelanggaranSantri::where('status', 'dicatat')->count();

        $slotUtama = $this->presensiService->kodeSlotAktif()[0] ?? null;
        $hadirMalam = 0;
        $totalMalam = $santriBinaan;
        if ($slotUtama !== null) {
            $hadirMalam = PresensiAsrama::where('tanggal', now()->toDateString())
                ->where('slot', $slotUtama)
                ->where('status', 'hadir')
                ->count();
        }

        $kamar = KamarAsrama::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->withCount(['penempatanAktif as jumlah'])
            ->with('blok')
            ->orderBy('nomor_kamar')
            ->limit(20)
            ->get()
            ->map(function (KamarAsrama $k) use ($slotUtama): array {
                $hadir = $slotUtama === null ? 0 : PresensiAsrama::where('kamar_id', $k->id)
                    ->where('tanggal', now()->toDateString())
                    ->where('slot', $slotUtama)
                    ->where('status', 'hadir')
                    ->count();

                return [
                    'id' => $k->id,
                    'nama' => 'Kamar '.$k->nomor_kamar,
                    'lantai' => $k->blok?->nama_blok ?? '—',
                    'jumlah' => $k->jumlah,
                    'hadir' => $hadir,
                    'status' => $k->jumlah > 0 && $hadir >= $k->jumlah ? 'lunas' : ($hadir > 0 ? 'menunggu' : 'ditolak'),
                ];
            });

        return Inertia::render('Pengasuhan/Beranda', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'ringkasan' => [
                'santri_binaan' => $santriBinaan,
                'izin_aktif' => $izinAktif,
                'pelanggaran_pending' => $pelanggaranPending,
                'kehadiran_malam' => "{$hadirMalam}/{$totalMalam}",
            ],
            'kamar' => $kamar,
        ]);
    }

    public function presensiAsrama(Request $request): Response
    {
        $tenantId = tenant('id');
        $kamarId = $request->query('kamar_id');

        $kamar = $kamarId
            ? KamarAsrama::where('tenant_id', $tenantId)->with('blok')->find($kamarId)
            : KamarAsrama::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('nomor_kamar')->with('blok')->first();

        $slotOptions = collect($this->presensiService->slotAktif())
            ->map(fn (array $s): array => ['value' => $s['kode'], 'label' => $s['label']])
            ->values()
            ->all();

        $tanggal = now()->toDateString();
        $slot = (string) $request->query('slot', $slotOptions[0]['value'] ?? 'subuh');

        $santri = [];
        if ($kamar !== null) {
            $santriIds = PenempatanAsrama::where('kamar_id', $kamar->id)
                ->where('is_current', true)
                ->pluck('santri_id');

            $statusPerSantri = PresensiAsrama::where('kamar_id', $kamar->id)
                ->where('tanggal', $tanggal)
                ->where('slot', $slot)
                ->pluck('status', 'santri_id');

            $santri = Santri::whereIn('id', $santriIds)
                ->with('kelas')
                ->orderBy('nama')
                ->get()
                ->map(fn (Santri $s): array => [
                    'id' => $s->id,
                    'nama' => $s->nama,
                    'kelas' => $s->kelas?->nama_kelas ?? '—',
                    'status' => $statusPerSantri[$s->id] ?? 'hadir',
                ])
                ->all();
        }

        return Inertia::render('Pengasuhan/PresensiAsrama', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'kamarId' => $kamar?->id,
            'kamar' => $kamar ? ('Kamar '.$kamar->nomor_kamar.' · '.($kamar->blok?->nama_blok ?? '—')) : 'Belum ada kamar aktif',
            'slot' => $slot,
            'slotOptions' => $slotOptions,
            'santri' => $santri,
        ]);
    }

    public function simpanPresensi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kamar_id' => ['required', 'integer'],
            'slot' => ['required', 'string'],
            'santri' => ['required', 'array', 'min:1'],
            'santri.*.santri_id' => ['required', 'integer'],
            'santri.*.status' => ['required', 'string'],
        ]);

        $kamar = KamarAsrama::where('tenant_id', tenant('id'))->findOrFail($data['kamar_id']);

        try {
            $this->presensiService->simpanPresensi(
                $kamar,
                null,
                now()->startOfDay(),
                $data['slot'],
                $data['santri'],
                Auth::user(),
            );
        } catch (Throwable $e) {
            return back()->withErrors(['presensi' => $e->getMessage()]);
        }

        return redirect()->route('tenant.pengasuhan.presensi', ['kamar_id' => $kamar->id, 'slot' => $data['slot']])
            ->with('success', 'Presensi tersimpan.');
    }

    public function disiplin(): Response
    {
        $pelanggaran = PelanggaranSantri::whereIn('status', ['dicatat', 'diverifikasi', 'sanksi_dijalankan'])
            ->whereMonth('waktu_kejadian', now()->month)
            ->whereYear('waktu_kejadian', now()->year)
            ->with(['santri', 'jenis'])
            ->orderByDesc('waktu_kejadian')
            ->limit(50)
            ->get();

        $totalPoin = (int) $pelanggaran->sum(fn (PelanggaranSantri $p) => $p->jenis?->poin ?? 0);

        return Inertia::render('Pengasuhan/Disiplin', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'bulan' => now()->translatedFormat('F Y'),
            'totalPoin' => $totalPoin,
            'pelanggaran' => $pelanggaran->map(fn (PelanggaranSantri $p): array => [
                'id' => $p->id,
                'santri' => $p->santri?->nama ?? '—',
                'kelas' => $p->santri?->kelas?->nama_kelas ?? '—',
                'kategori' => $p->jenis?->kategori ?? '—',
                'jenis' => $p->jenis?->nama ?? '—',
                'poin' => $p->jenis?->poin ?? 0,
                'tanggal' => $p->waktu_kejadian?->translatedFormat('d M Y'),
                'status' => $p->status,
            ]),
        ]);
    }

    public function catatPelanggaranForm(): Response
    {
        $tenantId = tenant('id');

        $santriOptions = Santri::where('tenant_id', $tenantId)
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->limit(300)
            ->get(['id', 'nama'])
            ->map(fn (Santri $s): array => ['id' => $s->id, 'nama' => $s->nama]);

        $jenisOptions = JenisPelanggaran::where('tenant_id', $tenantId)
            ->where('aktif', true)
            ->where('kategori', '!=', 'prestasi')
            ->orderBy('kategori')
            ->get()
            ->map(fn (JenisPelanggaran $j): array => ['value' => (string) $j->id, 'label' => "{$j->nama} ({$j->kategori}, {$j->poin} poin)"]);

        return Inertia::render('Pengasuhan/CatatPelanggaran', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'santriOptions' => $santriOptions,
            'jenisOptions' => $jenisOptions,
        ]);
    }

    public function catatPelanggaranStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'santri_id' => ['required', 'integer'],
            'jenis_pelanggaran_id' => ['required', 'integer'],
            'kronologi' => ['required', 'string', 'max:2000'],
            'lokasi' => ['nullable', 'string', 'max:255'],
        ]);

        $santri = Santri::where('tenant_id', tenant('id'))->findOrFail($data['santri_id']);
        $jenis = JenisPelanggaran::where('tenant_id', tenant('id'))->findOrFail($data['jenis_pelanggaran_id']);

        try {
            // paksaVerifikasiManual=true — pengasuhan bukan ustadz akademik,
            // catatannya WAJIB lewat verifikasi (konsisten dgn pola WP-21
            // untuk pencatat non-guru, anti penyalahgunaan poin).
            $this->disiplinService->catat($santri, $jenis, Auth::user(), [
                'lokasi' => $data['lokasi'] ?? null,
                'kronologi' => $data['kronologi'],
            ], paksaVerifikasiManual: true);
        } catch (Throwable $e) {
            return back()->withErrors(['catat' => $e->getMessage()])->withInput();
        }

        return redirect()->route('tenant.pengasuhan.disiplin')->with('success', 'Pelanggaran tercatat, menunggu verifikasi.');
    }

    public function izin(): Response
    {
        $daftar = IzinSantri::with('santri')
            ->orderByDesc('tanggal_mulai')
            ->limit(50)
            ->get()
            ->map(fn (IzinSantri $i): array => [
                'id' => $i->id,
                'santri' => $i->santri?->nama ?? '—',
                'kelas' => $i->santri?->kelas?->nama_kelas ?? '—',
                'jenis' => $i->jenis_izin,
                'keperluan' => $i->alasan,
                'mulai' => $i->tanggal_mulai?->translatedFormat('d M Y'),
                'selesai' => $i->tanggal_selesai?->translatedFormat('d M Y'),
                'status' => $i->status,
                'sudah_kembali' => (bool) $i->sudah_kembali,
            ]);

        return Inertia::render('Pengasuhan/Izin', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'daftar' => $daftar,
        ]);
    }
}
