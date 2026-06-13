<?php

declare(strict_types=1);

namespace App\Http\Controllers\DemoApps;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PWA Demo "Pengabdian Santri" (Khidmah) — showcase Vue/Inertia.
 *
 * Persona: santri pengabdian/khidmah. SEMUA data DUMMY inline (tidak menyentuh
 * DB/model apa pun kecuali Auth::user()?->name untuk sapaan) sehingga layar
 * selalu penuh & nol 500 saat peragaan. Aksi tulis di Vue bersifat optimistic
 * (tidak POST ke server). Gate: middleware('auth') saja — tanpa permission baru.
 *
 * Layar: Beranda, Jadwal, Presensi, Laporan, Profil.
 */
class PengabdianDemoController extends Controller
{
    /**
     * Item bottom-nav (kontrak {key,label,href,active}). Tiap key dipetakan ke
     * ikon whitelist via BottomNav: beranda->home, jadwal->calendar,
     * presensi->check-circle, laporan->document, profil->user.
     */
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

    /** Hak akses opsional (demo selalu mengizinkan) — disuntik ke tiap layar. */
    private function can(): array
    {
        return [
            'lapor' => true,
            'presensi' => true,
        ];
    }

    private function userName(): string
    {
        return Auth::user()?->name ?: 'Santri Pengabdian';
    }

    public function beranda(): Response
    {
        return Inertia::render('Pengabdian/Beranda', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'userName' => $this->userName(),
            'penugasan' => [
                'unit' => 'Khidmah Kebersihan Masjid',
                'periode' => 'Gelombang Syawal 1447 H',
                'pembimbing' => 'Ustadz Lukman Hakim',
            ],
            'stats' => [
                ['key' => 'kegiatan', 'label' => 'Kegiatan', 'value' => '24', 'hint' => 'Sepanjang periode', 'icon' => 'calendar', 'tone' => 'emerald'],
                ['key' => 'kehadiran', 'label' => 'Kehadiran', 'value' => '92%', 'hint' => '22 hadir, 2 izin', 'icon' => 'check-circle', 'tone' => 'teal'],
                ['key' => 'laporan', 'label' => 'Laporan', 'value' => '8', 'hint' => '6 diverifikasi', 'icon' => 'document', 'tone' => 'amber'],
            ],
            'tugasTerdekat' => [
                ['id' => 1, 'judul' => 'Bersih-bersih Masjid Jami', 'waktu' => 'Hari ini · 16.30 WIB', 'lokasi' => 'Masjid Jami Pusat', 'status' => 'aktif'],
                ['id' => 2, 'judul' => 'Mengajar TPQ Kelas Iqra 3', 'waktu' => 'Besok · 06.00 WIB', 'lokasi' => 'Aula TPQ Selatan', 'status' => 'info'],
                ['id' => 3, 'judul' => 'Piket Dapur Sore', 'waktu' => 'Kamis · 15.00 WIB', 'lokasi' => 'Dapur Umum', 'status' => 'info'],
            ],
        ]);
    }

    public function jadwal(): Response
    {
        return Inertia::render('Pengabdian/Jadwal', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'mendatang' => [
                ['id' => 1, 'judul' => 'Khidmah Kebersihan Masjid', 'waktu' => 'Senin, 12 Mei · 16.30', 'lokasi' => 'Masjid Jami Pusat', 'pembimbing' => 'Ustadz Lukman', 'durasi' => '2 jam', 'catatan' => 'Fokus area shaf depan & tempat wudhu. Bawa alat kebersihan dari gudang B.'],
                ['id' => 2, 'judul' => 'Pengajaran TPQ Iqra 3', 'waktu' => 'Selasa, 13 Mei · 06.00', 'lokasi' => 'Aula TPQ Selatan', 'pembimbing' => 'Ustadzah Mariam', 'durasi' => '1,5 jam', 'catatan' => 'Lanjutan halaman 18. Siapkan kartu prestasi tiap santri TPQ.'],
                ['id' => 3, 'judul' => 'Piket Dapur Sore', 'waktu' => 'Kamis, 15 Mei · 15.00', 'lokasi' => 'Dapur Umum', 'pembimbing' => 'Ustadz Sahal', 'durasi' => '3 jam', 'catatan' => 'Bantu persiapan makan malam 320 porsi. Patuhi protokol higienis.'],
            ],
            'selesai' => [
                ['id' => 4, 'judul' => 'Khidmah Kebersihan Masjid', 'waktu' => 'Jumat, 9 Mei · 16.30', 'lokasi' => 'Masjid Jami Pusat', 'pembimbing' => 'Ustadz Lukman', 'durasi' => '2 jam', 'catatan' => 'Selesai tepat waktu. Kehadiran penuh.'],
                ['id' => 5, 'judul' => 'Pengajaran TPQ Iqra 3', 'waktu' => 'Rabu, 7 Mei · 06.00', 'lokasi' => 'Aula TPQ Selatan', 'pembimbing' => 'Ustadzah Mariam', 'durasi' => '1,5 jam', 'catatan' => '12 santri hadir. Materi tercapai.'],
            ],
        ]);
    }

    public function presensi(): Response
    {
        return Inertia::render('Pengabdian/Presensi', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'userName' => $this->userName(),
            'kegiatanHariIni' => [
                ['id' => 1, 'judul' => 'Khidmah Kebersihan Masjid', 'waktu' => '16.30 WIB', 'lokasi' => 'Masjid Jami Pusat', 'status' => null],
                ['id' => 2, 'judul' => 'Pengajaran TPQ Iqra 3', 'waktu' => '06.00 WIB', 'lokasi' => 'Aula TPQ Selatan', 'status' => 'hadir'],
                ['id' => 3, 'judul' => 'Piket Dapur Sore', 'waktu' => '15.00 WIB', 'lokasi' => 'Dapur Umum', 'status' => null],
            ],
        ]);
    }

    public function laporan(): Response
    {
        return Inertia::render('Pengabdian/Laporan', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'userName' => $this->userName(),
            'kegiatanPilihan' => [
                ['id' => 1, 'judul' => 'Khidmah Kebersihan Masjid'],
                ['id' => 2, 'judul' => 'Pengajaran TPQ Iqra 3'],
                ['id' => 3, 'judul' => 'Piket Dapur Sore'],
            ],
            'laporan' => [
                ['id' => 1, 'judul' => 'Kebersihan Masjid Jami', 'tanggal' => '9 Mei 2026', 'kegiatan' => 'Khidmah Kebersihan Masjid', 'status' => 'terverifikasi', 'ringkasan' => 'Area shaf depan & tempat wudhu dibersihkan. Tidak ada kendala.'],
                ['id' => 2, 'judul' => 'Pengajaran TPQ Iqra 3', 'tanggal' => '7 Mei 2026', 'kegiatan' => 'Pengajaran TPQ Iqra 3', 'status' => 'terverifikasi', 'ringkasan' => '12 santri hadir, materi halaman 17 tuntas.'],
                ['id' => 3, 'judul' => 'Piket Dapur Sore', 'tanggal' => '5 Mei 2026', 'kegiatan' => 'Piket Dapur Sore', 'status' => 'menunggu', 'ringkasan' => 'Membantu masak 320 porsi untuk makan malam.'],
                ['id' => 4, 'judul' => 'Khidmah Kebersihan Masjid', 'tanggal' => '4 Mei 2026', 'kegiatan' => 'Khidmah Kebersihan Masjid', 'status' => 'draft', 'ringkasan' => 'Catatan kasar, belum dikirim ke pembimbing.'],
            ],
        ]);
    }

    public function profil(): Response
    {
        return Inertia::render('Pengabdian/Profil', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'profil' => [
                'nama' => $this->userName(),
                'nis' => '24.1147.0089',
                'unit' => 'Khidmah Kebersihan Masjid',
                'angkatan' => 'Angkatan 1447 H',
                'pembimbing' => 'Ustadz Lukman Hakim',
            ],
            'ringkasan' => [
                ['key' => 'jam', 'label' => 'Total Jam', 'value' => '148', 'hint' => 'Jam khidmah', 'icon' => 'clock', 'tone' => 'emerald'],
                ['key' => 'poin', 'label' => 'Poin Khidmah', 'value' => '920', 'hint' => 'Peringkat 3 unit', 'icon' => 'heart', 'tone' => 'rose'],
            ],
            'riwayat' => [
                ['id' => 1, 'judul' => 'Khidmah Kebersihan Masjid', 'tanggal' => '9 Mei 2026', 'jam' => '2 jam', 'status' => 'terverifikasi'],
                ['id' => 2, 'judul' => 'Pengajaran TPQ Iqra 3', 'tanggal' => '7 Mei 2026', 'jam' => '1,5 jam', 'status' => 'terverifikasi'],
                ['id' => 3, 'judul' => 'Piket Dapur Sore', 'tanggal' => '5 Mei 2026', 'jam' => '3 jam', 'status' => 'terverifikasi'],
                ['id' => 4, 'judul' => 'Khidmah Kebersihan Masjid', 'tanggal' => '2 Mei 2026', 'jam' => '2 jam', 'status' => 'terverifikasi'],
            ],
            'sertifikat' => [
                ['id' => 1, 'judul' => 'Sertifikat Khidmah Gelombang Rajab', 'tanggal' => 'Terbit 1 Mei 2026', 'status' => 'aktif'],
                ['id' => 2, 'judul' => 'Piagam Penghargaan Pengajar TPQ', 'tanggal' => 'Terbit 14 April 2026', 'status' => 'aktif'],
            ],
        ]);
    }
}
