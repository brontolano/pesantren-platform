<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\StafNav;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kegiatan\Models\KunjunganWali;
use Modules\Kegiatan\Models\LogGerbang;
use Modules\Kegiatan\Models\PengajuanJenguk;
use Modules\Kegiatan\Services\GerbangService;
use Modules\Kegiatan\Services\PenjengukanService;
use Modules\Kesantrian\Models\Santri;

/**
 * PWA "Pos Keamanan" — persona petugas keamanan/satpam pesantren.
 * Menggantikan KeamananDemoController (100% dummy): data nyata via
 * Modules\Kegiatan\GerbangService/PenjengukanService — layanan yang SAMA
 * yang sudah dipakai PortalGuruGerbangController (mode "keamanan" PWA Guru)
 * dan panel admin (LogGerbangPanel/KunjunganWaliResource). Tidak ada logika
 * baru — controller ini hanya membungkus service yang sama untuk persona
 * petugas keamanan (bukan guru).
 *
 * SCOPE DIKURANGI — layar "Ronda Malam" (checklist titik patroli) di demo
 * lama TIDAK diportal: tidak ada model/tabel patroli/ronda apa pun di
 * sistem ini. Memaksakan akan jadi data palsu (sama semangatnya dengan
 * keputusan scope POS Kantin di PR#413 yang membuang layar "Stok" karena
 * tak ada model inventaris nyata). Rute /keamanan/ronda dihapus.
 *
 * Gate: middleware('auth') + can:view_kegiatan. TIDAK ada role/permission
 * "keamanan" khusus di RolesAndPermissionsSeeder — permission granular
 * terdekat & paling presisi untuk data gerbang/tamu (tabel log_gerbang,
 * kunjungan_wali, pengajuan_jenguk — semua di bawah modul Kegiatan) adalah
 * `view_kegiatan`, permission yang SUDAH dipakai LogGerbangPanel &
 * KunjunganWaliResource::canAccess() di panel admin. Role `operator_divisi`
 * (kandidat "divisi generik" seperti Humas/Kepegawaian) TIDAK memilikinya
 * (hanya view_dashboard/view_santri/input_belanja/view_ledger) — tidak
 * cocok untuk data ini. Role yang sudah punya `view_kegiatan`:
 * admin_pesantren, tu, kepala.
 */
class KeamananController extends Controller
{
    public function __construct(
        private readonly GerbangService $gerbang,
        private readonly PenjengukanService $penjengukan,
    ) {}

    private function nav(): array
    {
        // Bottom-nav STANDAR mockup (Beranda/Menu/Aktivitas/Akun) - owner 2026-07-06.
        return StafNav::items();
    }

    private function can(): array
    {
        return [
            'catat_gerbang' => true,
            'kelola_tamu' => true,
        ];
    }

    public function beranda(): Response
    {
        $today = Carbon::today();

        // "Di luar" = santri dg log KELUAR hari ini yg belum punya log MASUK
        // sesudahnya (per-santri, dibandingkan waktu log terakhir).
        $terakhirPerSantri = LogGerbang::query()
            ->whereDate('waktu', $today)
            ->selectRaw('santri_id, MAX(waktu) as waktu_terakhir')
            ->groupBy('santri_id')
            ->pluck('waktu_terakhir', 'santri_id');

        $diLuar = 0;
        foreach ($terakhirPerSantri as $santriId => $waktuTerakhir) {
            $arahTerakhir = LogGerbang::query()
                ->where('santri_id', $santriId)
                ->where('waktu', $waktuTerakhir)
                ->value('arah');
            if ($arahTerakhir === 'keluar') {
                $diLuar++;
            }
        }

        $tamuHadir = KunjunganWali::query()
            ->whereDate('tanggal', $today)
            ->where('status', 'hadir')
            ->count();

        $izinAktif = IzinSantri::query()
            ->where('status', 'disetujui')
            ->where('sudah_kembali', false)
            ->whereDate('tanggal_mulai', '<=', $today)
            ->whereDate('tanggal_selesai', '>=', $today)
            ->count();

        $tanpaIzin = LogGerbang::query()
            ->whereDate('waktu', $today)
            ->where('is_tanpa_izin', true)
            ->count();

        $logTerkini = LogGerbang::query()
            ->with('santri:id,nis,nama,kelas_id')
            ->whereDate('waktu', $today)
            ->orderByDesc('waktu')
            ->limit(5)
            ->get()
            ->map(fn (LogGerbang $log): array => [
                'nama' => $log->santri?->nama ?? '—',
                'kelas' => $log->santri?->kelas?->nama ?? '-',
                'aksi' => $log->is_tanpa_izin
                    ? 'Keluar tanpa izin'
                    : ($log->arah === 'keluar' ? 'Keluar (izin pulang)' : 'Masuk'),
                'jam' => $log->waktu->format('H:i'),
                'status' => $log->is_tanpa_izin ? 'ditolak' : 'aktif',
            ])->values()->all();

        return Inertia::render('Keamanan/Beranda', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'userName' => Auth::user()?->name,
            'stats' => [
                ['key' => 'di_luar', 'label' => 'Santri di Luar', 'value' => $diLuar, 'tone' => 'amber', 'icon' => 'arrow-up-right', 'hint' => 'Tercatat keluar gerbang'],
                ['key' => 'tamu', 'label' => 'Tamu Hadir', 'value' => $tamuHadir, 'tone' => 'blue', 'icon' => 'user', 'hint' => 'Buku tamu hari ini'],
                ['key' => 'izin', 'label' => 'Izin Aktif', 'value' => $izinAktif, 'tone' => 'emerald', 'icon' => 'check-circle', 'hint' => 'Berizin & masih berlaku'],
                ['key' => 'alert', 'label' => 'Tanpa Izin', 'value' => $tanpaIzin, 'tone' => 'rose', 'icon' => 'ban', 'hint' => 'Keluar tanpa izin'],
            ],
            'menu' => [
                ['key' => 'gerbang', 'href' => '/keamanan/gerbang', 'icon' => 'qr', 'tone' => 'emerald', 'label' => 'Scan Gerbang', 'desc' => 'Cek & catat keluar/masuk'],
                ['key' => 'tamu', 'href' => '/keamanan/tamu', 'icon' => 'user', 'tone' => 'blue', 'label' => 'Buku Tamu', 'desc' => 'Daftar kunjungan hari ini'],
                ['key' => 'log', 'href' => '/keamanan/log', 'icon' => 'clock', 'tone' => 'teal', 'label' => 'Log Gerbang', 'desc' => 'Riwayat keluar-masuk'],
            ],
            'terkini' => $logTerkini,
        ]);
    }

    public function gerbang(Request $request): Response
    {
        $nis = trim((string) $request->query('nis', ''));
        $santriCard = null;

        if ($nis !== '') {
            $santri = Santri::query()->where('nis', $nis)->where('status', 'aktif')->first();

            if ($santri !== null) {
                $kondisi = $this->gerbang->statusSantri($santri);
                $izin = $kondisi['izin'];

                $santriCard = [
                    'id' => $santri->id,
                    'nis' => (string) $santri->nis,
                    'nama' => $santri->nama,
                    'kelas' => $santri->kelas?->nama ?? '-',
                    'foto' => $santri->urlFotoAman(),
                    'status' => $kondisi['status'],
                    'izin' => $izin === null ? null : [
                        'id' => $izin->id,
                        'jenis_izin' => $izin->jenis_izin,
                        'tanggal_mulai' => $izin->tanggal_mulai->format('d-m-Y'),
                        'tanggal_selesai' => $izin->tanggal_selesai->format('d-m-Y'),
                    ],
                ];
            } else {
                $santriCard = ['tidak_ditemukan' => true, 'nis' => $nis];
            }
        }

        return Inertia::render('Keamanan/Gerbang', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'nis' => $nis,
            'santriCard' => $santriCard,
        ]);
    }

    public function catatGerbang(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'santri_id' => ['required', 'integer', 'exists:santri,id'],
            'arah' => ['required', 'in:keluar,masuk'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $santri = Santri::findOrFail($data['santri_id']);
        $log = $this->gerbang->catat($santri, $data['arah'], Auth::user(), $data['catatan'] ?? null);

        $pesan = $log->is_tanpa_izin
            ? 'Dicatat KELUAR TANPA IZIN — pengasuhan dinotifikasi.'
            : "Dicatat: {$santri->nama} {$data['arah']}.";

        return back()->with('success', $pesan);
    }

    public function tamu(): Response
    {
        $tamu = KunjunganWali::query()
            ->with('santri:id,nis,nama,kelas_id')
            ->whereDate('tanggal', Carbon::today())
            ->orderByDesc('jam_datang')
            ->limit(50)
            ->get()
            ->map(fn (KunjunganWali $k): array => [
                'id' => $k->id,
                'nama' => $k->nama_pengunjung,
                'asal' => $k->hubungan,
                'dijenguk' => trim(($k->santri?->nama ?? '—').' ('.($k->santri?->kelas?->nama ?? '-').')'),
                'jam' => $k->jam_datang ?? '—',
                'keperluan' => $k->keperluan ?? '—',
                'hadir' => $k->status === 'hadir',
            ])->values()->all();

        $jengukHariIni = PengajuanJenguk::query()
            ->with(['pengaju:id,name', 'santri:id,nis,nama'])
            ->whereDate('tanggal', Carbon::today())
            ->where('status', 'dikonfirmasi')
            ->get()
            ->map(fn (PengajuanJenguk $p): array => [
                'id' => $p->id,
                'santri_id' => $p->santri_id,
                'pengaju' => $p->pengaju?->name,
                'santri' => $p->santri?->nama,
                'catatan' => $p->catatan,
            ])->values()->all();

        return Inertia::render('Keamanan/Tamu', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'tamu' => $tamu,
            'jengukHariIni' => $jengukHariIni,
            'walkInBoleh' => $this->penjengukan->jadwal()['walk_in_boleh'],
        ]);
    }

    public function jengukHadir(Request $request, int $pengajuan): RedirectResponse
    {
        $data = $request->validate([
            'nama_pengunjung' => ['nullable', 'string', 'max:255'],
            'hubungan' => ['nullable', 'string', 'max:50'],
        ]);

        $row = PengajuanJenguk::findOrFail($pengajuan);
        $this->penjengukan->tandaiHadir($row, $data['nama_pengunjung'] ?? null, $data['hubungan'] ?? null);

        return back()->with('success', 'Tamu ditandai hadir & kunjungan tercatat.');
    }

    public function jengukWalkIn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'santri_id' => ['required', 'integer', 'exists:santri,id'],
            'nama_pengunjung' => ['required', 'string', 'max:255'],
            'hubungan' => ['required', 'string', 'max:50'],
            'keperluan' => ['nullable', 'string', 'max:500'],
        ]);

        $santri = Santri::findOrFail($data['santri_id']);
        $this->penjengukan->walkIn($santri, $data['nama_pengunjung'], $data['hubungan'], $data['keperluan'] ?? null);

        return back()->with('success', 'Kunjungan walk-in tercatat.');
    }

    public function log(): Response
    {
        $log = LogGerbang::query()
            ->with('santri:id,nis,nama,kelas_id')
            ->whereDate('waktu', Carbon::today())
            ->orderByDesc('waktu')
            ->limit(50)
            ->get()
            ->map(fn (LogGerbang $l): array => [
                'id' => $l->id,
                'nama' => $l->santri?->nama ?? '—',
                'kelas' => $l->santri?->kelas?->nama ?? '-',
                'arah' => $l->arah,
                'izin' => ! $l->is_tanpa_izin,
                'jam' => $l->waktu->format('H:i'),
                'ket' => $l->is_tanpa_izin ? 'Tanpa izin' : ($l->catatan ?: ($l->izin?->jenis_izin ?? '—')),
            ])->values()->all();

        return Inertia::render('Keamanan/Log', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'log' => $log,
        ]);
    }
}
