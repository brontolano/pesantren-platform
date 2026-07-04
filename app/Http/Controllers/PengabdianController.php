<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kegiatan\Models\Kegiatan;
use Modules\Kegiatan\Models\PresensiKegiatan;
use Modules\Kesantrian\Models\Santri;

/**
 * PWA "Pengabdian" (khidmah santri) — menggantikan PengabdianDemoController.
 *
 * SCOPE DIKURANGI (mirip POS Kantin PR#413): tidak ada model/permission khusus
 * "pengabdian" atau "penempatan khidmah" di sistem ini. Riset menemukan:
 *  - Tidak ada role/permission bernama pengabdian. `Modules\Payroll\Khidmah`
 *    HANYA milik staf/payroll (poin kehadiran mengajar guru) — domain BEDA,
 *    sengaja TIDAK dipakai di sini agar tidak mencampur data staf ke santri.
 *  - Tidak ada field alumni/unit/divisi/khidmah pada model Santri.
 *  - Yang REAL & bisa dipakai jujur: `Modules\Kegiatan\Kegiatan` (jadwal
 *    kegiatan generik: nama, mulai/selesai, lokasi, status, penanggung_jawab)
 *    + `Modules\Kegiatan\PresensiKegiatan` (presensi per-santri: status,
 *    keterangan) — sudah dipakai KegiatanResource di panel admin (staf,
 *    gerbang view_kegiatan/manage_kegiatan).
 *  - TIDAK ADA konvensi `jenis` = 'pengabdian' di data seeder/migrasi (nilai
 *    default hanya umum/wajib/sunnah) — memfilter ke jenis itu akan SELALU
 *    kosong (bukan representatif). Maka layar Jadwal/Presensi di sini
 *    menampilkan SEMUA Kegiatan yang benar-benar diikuti santri login (via
 *    PresensiKegiatan nyata), bukan dipersempit ke tag yang tidak pernah ada.
 *  - "Laporan" & "Profil > sertifikat/poin khidmah" pada demo lama adalah
 *    fabrikasi murni (tidak ada model laporan/poin/sertifikat santri).
 *    DIHAPUS dari versi nyata ini — ringkasan kehadiran (jumlah hadir/izin/
 *    alpha/sakit) dihitung langsung dari PresensiKegiatan, bukan diciptakan.
 *
 * Gate: middleware('auth') saja — konsisten dengan pola santri-app/portal
 * lain (santri diidentifikasi via Santri.user_id, bukan permission staf;
 * lihat AppLauncher::hasStaffCapability yang TIDAK menyertakan santri).
 * BUKAN entry STAFF_APPS — ini aplikasi santri, bukan aplikasi staf.
 */
class PengabdianController extends Controller
{
    private function nav(): array
    {
        $base = '/pengabdian';
        $items = [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => $base, 'route' => 'beranda', 'icon' => 'home'],
            ['key' => 'jadwal', 'label' => 'Jadwal', 'href' => "{$base}/jadwal", 'route' => 'jadwal', 'icon' => 'calendar'],
            ['key' => 'presensi', 'label' => 'Presensi', 'href' => "{$base}/presensi", 'route' => 'presensi', 'icon' => 'check-circle'],
            ['key' => 'laporan', 'label' => 'Laporan', 'href' => "{$base}/laporan", 'route' => 'laporan', 'icon' => 'document'],
            ['key' => 'profil', 'label' => 'Profil', 'href' => "{$base}/profil", 'route' => 'profil', 'icon' => 'user'],
        ];

        return collect($items)
            ->map(fn (array $i): array => [
                'key' => $i['key'],
                'label' => $i['label'],
                'href' => $i['href'],
                'icon' => $i['icon'],
                'active' => request()->routeIs("tenant.pengabdian.{$i['route']}"),
            ])
            ->all();
    }

    private function userName(): string
    {
        return Auth::user()?->name ?: 'Santri';
    }

    /** Santri login (via Santri.user_id), atau null bila akun tak tertaut. */
    private function santriLogin(): ?Santri
    {
        $userId = Auth::id();
        if ($userId === null) {
            return null;
        }

        return Santri::where('user_id', $userId)->first();
    }

    /** Semua kegiatan yang PersembahyangKegiatan-nya nyata untuk santri ini. */
    private function presensiSantri(?Santri $santri)
    {
        if ($santri === null) {
            return collect();
        }

        return PresensiKegiatan::where('santri_id', $santri->id)
            ->with('kegiatan')
            ->whereHas('kegiatan')
            ->get()
            ->sortByDesc(fn (PresensiKegiatan $p) => $p->kegiatan?->mulai)
            ->values();
    }

    public function beranda(): Response
    {
        $santri = $this->santriLogin();
        $presensi = $this->presensiSantri($santri);

        $sekarang = now();
        $mendatang = $presensi->filter(fn (PresensiKegiatan $p) => $p->kegiatan?->mulai?->greaterThanOrEqualTo($sekarang->startOfDay()))
            ->sortBy(fn (PresensiKegiatan $p) => $p->kegiatan?->mulai)
            ->take(3);

        $hadir = $presensi->where('status', 'hadir')->count();
        $total = $presensi->count();
        $persenHadir = $total > 0 ? round($hadir / $total * 100).'%' : '—';

        return Inertia::render('Pengabdian/Beranda', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'penugasan' => $santri === null ? null : [
                'nama' => $santri->nama,
                'nis' => $santri->nis,
                'kelas' => $santri->kelas?->nama_kelas ?? '-',
            ],
            'stats' => [
                ['key' => 'kegiatan', 'label' => 'Kegiatan Diikuti', 'value' => (string) $total, 'hint' => 'Tercatat presensi', 'icon' => 'calendar', 'tone' => 'emerald'],
                ['key' => 'kehadiran', 'label' => 'Kehadiran', 'value' => $persenHadir, 'hint' => "{$hadir} dari {$total} hadir", 'icon' => 'check-circle', 'tone' => 'teal'],
            ],
            'tugasTerdekat' => $mendatang->map(fn (PresensiKegiatan $p): array => [
                'id' => $p->kegiatan->id,
                'judul' => $p->kegiatan->nama,
                'waktu' => $p->kegiatan->mulai?->translatedFormat('D, d M · H:i'),
                'lokasi' => $p->kegiatan->lokasi ?? '-',
                'status' => $p->kegiatan->status,
            ])->values(),
        ]);
    }

    public function jadwal(): Response
    {
        $santri = $this->santriLogin();
        $presensi = $this->presensiSantri($santri);
        $sekarang = now();

        $mendatang = $presensi->filter(fn (PresensiKegiatan $p) => $p->kegiatan?->mulai?->greaterThanOrEqualTo($sekarang))
            ->sortBy(fn (PresensiKegiatan $p) => $p->kegiatan?->mulai);
        $selesai = $presensi->filter(fn (PresensiKegiatan $p) => $p->kegiatan?->mulai?->lessThan($sekarang));

        $mapKegiatan = fn (PresensiKegiatan $p): array => [
            'id' => $p->kegiatan->id,
            'judul' => $p->kegiatan->nama,
            'waktu' => $p->kegiatan->mulai?->translatedFormat('l, d M · H:i'),
            'lokasi' => $p->kegiatan->lokasi ?? '-',
            'pembimbing' => $p->kegiatan->penanggungJawab?->name ?? '-',
            'catatan' => $p->kegiatan->deskripsi ?? '-',
        ];

        return Inertia::render('Pengabdian/Jadwal', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'mendatang' => $mendatang->map($mapKegiatan)->values(),
            'selesai' => $selesai->map($mapKegiatan)->values(),
        ]);
    }

    public function presensi(): Response
    {
        $santri = $this->santriLogin();
        $presensi = $this->presensiSantri($santri);
        $hariIni = $presensi->filter(fn (PresensiKegiatan $p) => $p->kegiatan?->mulai?->isToday());

        return Inertia::render('Pengabdian/Presensi', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'kegiatanHariIni' => $hariIni->map(fn (PresensiKegiatan $p): array => [
                'id' => $p->kegiatan->id,
                'judul' => $p->kegiatan->nama,
                'waktu' => $p->kegiatan->mulai?->format('H:i'),
                'lokasi' => $p->kegiatan->lokasi ?? '-',
                'status' => $p->status,
            ])->values(),
        ]);
    }

    /**
     * Laporan kehadiran — dihitung langsung dari PresensiKegiatan nyata
     * (status + keterangan). BUKAN sistem laporan naratif per-kegiatan
     * (tidak ada model itu) — demo lama memfabrikasi status
     * draft/menunggu/terverifikasi; versi nyata ini hanya menampilkan
     * ringkasan presensi yang benar-benar tercatat.
     */
    public function laporan(): Response
    {
        $santri = $this->santriLogin();
        $presensi = $this->presensiSantri($santri);

        return Inertia::render('Pengabdian/Laporan', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'laporan' => $presensi->map(fn (PresensiKegiatan $p): array => [
                'id' => $p->id,
                'judul' => $p->kegiatan->nama,
                'tanggal' => $p->kegiatan->mulai?->translatedFormat('d M Y'),
                'status' => $p->status,
                'ringkasan' => $p->keterangan ?: '-',
            ])->values(),
        ]);
    }

    public function profil(): Response
    {
        $santri = $this->santriLogin();
        $presensi = $this->presensiSantri($santri);

        $rekap = $presensi->groupBy('status')->map->count();

        return Inertia::render('Pengabdian/Profil', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'profil' => $santri === null ? null : [
                'nama' => $santri->nama,
                'nis' => $santri->nis,
                'kelas' => $santri->kelas?->nama_kelas ?? '-',
                'status' => $santri->status,
            ],
            'ringkasan' => [
                ['key' => 'hadir', 'label' => 'Hadir', 'value' => (string) ($rekap['hadir'] ?? 0), 'hint' => 'Kegiatan', 'icon' => 'check-circle', 'tone' => 'emerald'],
                ['key' => 'izin', 'label' => 'Izin/Sakit', 'value' => (string) (($rekap['izin'] ?? 0) + ($rekap['sakit'] ?? 0)), 'hint' => 'Kegiatan', 'icon' => 'clock', 'tone' => 'amber'],
                ['key' => 'alpha', 'label' => 'Alpha', 'value' => (string) ($rekap['alpha'] ?? 0), 'hint' => 'Kegiatan', 'icon' => 'x-circle', 'tone' => 'rose'],
            ],
            'riwayat' => $presensi->take(10)->map(fn (PresensiKegiatan $p): array => [
                'id' => $p->id,
                'judul' => $p->kegiatan->nama,
                'tanggal' => $p->kegiatan->mulai?->translatedFormat('d M Y'),
                'status' => $p->status,
            ])->values(),
        ]);
    }
}
