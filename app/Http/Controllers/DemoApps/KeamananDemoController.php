<?php

declare(strict_types=1);

namespace App\Http\Controllers\DemoApps;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PWA Demo "Pos Keamanan" — persona petugas keamanan/satpam pesantren.
 *
 * DEMO-GRADE: seluruh data adalah DUMMY INLINE (array PHP) supaya layar selalu
 * penuh & nol risiko 500 — TANPA query DB/model apa pun kecuali Auth::user()
 * untuk sapaan. Semua aksi tulis di sisi Vue bersifat optimistic (tidak POST).
 *
 * Rute di-gate middleware('auth') SAJA (tanpa can:) agar tak menambah permission
 * baru. Folder Vue: resources/js/Pages/Keamanan/*.
 */
class KeamananDemoController extends Controller
{
    /**
     * Item bottom-nav (kontrak {key,label,href,active}) untuk AppShell.
     * Audit ikon (lewat key) terhadap whitelist UiIcon:
     *   beranda→home, gerbang→qr, tamu→user, log→clock, ronda→check-circle.
     */
    private function nav(): array
    {
        $items = [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => '/keamanan', 'route' => 'beranda'],
            ['key' => 'gerbang', 'label' => 'Gerbang', 'href' => '/keamanan/gerbang', 'route' => 'gerbang'],
            ['key' => 'tamu', 'label' => 'Tamu', 'href' => '/keamanan/tamu', 'route' => 'tamu'],
            ['key' => 'log', 'label' => 'Log', 'href' => '/keamanan/log', 'route' => 'log'],
            ['key' => 'ronda', 'label' => 'Ronda', 'href' => '/keamanan/ronda', 'route' => 'ronda'],
        ];

        return collect($items)
            ->map(fn (array $i): array => [
                'key' => $i['key'],
                'label' => $i['label'],
                'href' => $i['href'],
                'active' => request()->routeIs("tenant.keamanan.{$i['route']}"),
            ])
            ->all();
    }

    /** Izin opsional (semua true di demo — tidak menambah permission). */
    private function can(): array
    {
        return [
            'catat_gerbang' => true,
            'kelola_tamu' => true,
            'isi_ronda' => true,
        ];
    }

    public function beranda(): Response
    {
        return Inertia::render('Keamanan/Beranda', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'userName' => Auth::user()?->name,
            'stats' => [
                ['key' => 'di_luar', 'label' => 'Santri di Luar', 'value' => 7, 'tone' => 'amber', 'icon' => 'arrow-up-right', 'hint' => 'Tercatat keluar gerbang'],
                ['key' => 'tamu', 'label' => 'Tamu Hadir', 'value' => 4, 'tone' => 'blue', 'icon' => 'user', 'hint' => 'Buku tamu hari ini'],
                ['key' => 'izin', 'label' => 'Izin Aktif', 'value' => 5, 'tone' => 'emerald', 'icon' => 'check-circle', 'hint' => 'Berizin & masih berlaku'],
                ['key' => 'alert', 'label' => 'Tanpa Izin', 'value' => 2, 'tone' => 'rose', 'icon' => 'ban', 'hint' => 'Keluar tanpa izin'],
            ],
            'menu' => [
                ['key' => 'gerbang', 'href' => '/keamanan/gerbang', 'icon' => 'qr', 'tone' => 'emerald', 'label' => 'Scan Gerbang', 'desc' => 'Cek & catat keluar/masuk'],
                ['key' => 'tamu', 'href' => '/keamanan/tamu', 'icon' => 'user', 'tone' => 'blue', 'label' => 'Buku Tamu', 'desc' => 'Daftar kunjungan hari ini'],
                ['key' => 'log', 'href' => '/keamanan/log', 'icon' => 'clock', 'tone' => 'teal', 'label' => 'Log Gerbang', 'desc' => 'Riwayat keluar-masuk'],
                ['key' => 'ronda', 'href' => '/keamanan/ronda', 'icon' => 'check-circle', 'tone' => 'amber', 'label' => 'Ronda Malam', 'desc' => 'Checklist 6 titik patroli'],
            ],
            'terkini' => [
                ['nama' => 'Ahmad Fauzi', 'kelas' => '3 Aliyah', 'aksi' => 'Keluar (izin pulang)', 'jam' => '13:42', 'status' => 'aktif'],
                ['nama' => 'Tamu: Bpk. Sukarno', 'kelas' => 'Menjenguk Rizky', 'aksi' => 'Tamu masuk', 'jam' => '13:20', 'status' => 'info'],
                ['nama' => 'Bilal Ramadhan', 'kelas' => '1 Tsanawiyah', 'aksi' => 'Keluar tanpa izin', 'jam' => '12:58', 'status' => 'ditolak'],
            ],
        ]);
    }

    public function gerbang(): Response
    {
        return Inertia::render('Keamanan/Gerbang', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            // Direktori dummy NIS → kartu santri (dipakai saat "cek").
            'direktori' => [
                '2024001' => ['nama' => 'Ahmad Fauzi', 'kelas' => '3 Aliyah', 'kamar' => 'Asrama Putra A-12', 'status' => 'aktif', 'statusLabel' => 'Berizin pulang', 'posisi' => 'luar', 'catatan' => 'Izin pulang s/d 15:00 hari ini'],
                '2024002' => ['nama' => 'Bilal Ramadhan', 'kelas' => '1 Tsanawiyah', 'kamar' => 'Asrama Putra B-04', 'status' => 'ditolak', 'statusLabel' => 'Tanpa izin', 'posisi' => 'dalam', 'catatan' => 'Tidak ada izin keluar tercatat'],
                '2024003' => ['nama' => 'Siti Khadijah', 'kelas' => '2 Aliyah', 'kamar' => 'Asrama Putri C-09', 'status' => 'menunggu', 'statusLabel' => 'Menunggu konfirmasi wali', 'posisi' => 'dalam', 'catatan' => 'Izin sakit, belum disetujui pengasuh'],
            ],
        ]);
    }

    public function tamu(): Response
    {
        return Inertia::render('Keamanan/Tamu', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'tamu' => [
                ['id' => 1, 'nama' => 'Bpk. Sukarno', 'asal' => 'Orang tua', 'dijenguk' => 'Rizky Maulana (2 Aliyah)', 'jam' => '13:20', 'keperluan' => 'Menjenguk', 'hadir' => true],
                ['id' => 2, 'nama' => 'Ibu Aisyah', 'asal' => 'Wali santri', 'dijenguk' => 'Fatimah Az-Zahra (1 Aliyah)', 'jam' => '11:05', 'keperluan' => 'Antar kebutuhan', 'hadir' => true],
                ['id' => 3, 'nama' => 'Bpk. Hasan', 'asal' => 'Paman', 'dijenguk' => 'Yusuf Abdullah (3 Tsanawiyah)', 'jam' => '10:30', 'keperluan' => 'Menjenguk', 'hadir' => true],
                ['id' => 4, 'nama' => 'Ibu Maryam', 'asal' => 'Orang tua', 'dijenguk' => 'Hafsah Salim (2 Tsanawiyah)', 'jam' => '09:50', 'keperluan' => 'Menjenguk', 'hadir' => true],
                ['id' => 5, 'nama' => 'Bpk. Umar Faruq', 'asal' => 'Kakak', 'dijenguk' => 'Zaid Umar (1 Aliyah)', 'jam' => '—', 'keperluan' => 'Menjenguk', 'hadir' => false],
            ],
        ]);
    }

    public function log(): Response
    {
        return Inertia::render('Keamanan/Log', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'log' => [
                ['id' => 1, 'nama' => 'Ahmad Fauzi', 'kelas' => '3 Aliyah', 'arah' => 'keluar', 'izin' => true, 'jam' => '13:42', 'ket' => 'Izin pulang'],
                ['id' => 2, 'nama' => 'Bpk. Sukarno (tamu)', 'kelas' => 'Menjenguk Rizky', 'arah' => 'masuk', 'izin' => true, 'jam' => '13:20', 'ket' => 'Tamu terdaftar'],
                ['id' => 3, 'nama' => 'Bilal Ramadhan', 'kelas' => '1 Tsanawiyah', 'arah' => 'keluar', 'izin' => false, 'jam' => '12:58', 'ket' => 'Tanpa izin'],
                ['id' => 4, 'nama' => 'Khalid Walid', 'kelas' => '2 Aliyah', 'arah' => 'masuk', 'izin' => true, 'jam' => '12:30', 'ket' => 'Kembali dari poliklinik'],
                ['id' => 5, 'nama' => 'Salman Alfarisi', 'kelas' => '3 Tsanawiyah', 'arah' => 'keluar', 'izin' => true, 'jam' => '11:40', 'ket' => 'Izin berobat'],
                ['id' => 6, 'nama' => 'Ibu Aisyah (tamu)', 'kelas' => 'Wali Fatimah', 'arah' => 'masuk', 'izin' => true, 'jam' => '11:05', 'ket' => 'Tamu terdaftar'],
                ['id' => 7, 'nama' => 'Hamzah Abdul', 'kelas' => '1 Aliyah', 'arah' => 'keluar', 'izin' => false, 'jam' => '10:48', 'ket' => 'Tanpa izin'],
                ['id' => 8, 'nama' => 'Yusuf Abdullah', 'kelas' => '3 Tsanawiyah', 'arah' => 'masuk', 'izin' => true, 'jam' => '10:35', 'ket' => 'Kembali dari rumah'],
                ['id' => 9, 'nama' => 'Bpk. Hasan (tamu)', 'kelas' => 'Paman Yusuf', 'arah' => 'masuk', 'izin' => true, 'jam' => '10:30', 'ket' => 'Tamu terdaftar'],
                ['id' => 10, 'nama' => 'Utsman Affan', 'kelas' => '2 Tsanawiyah', 'arah' => 'keluar', 'izin' => true, 'jam' => '09:55', 'ket' => 'Izin lomba'],
                ['id' => 11, 'nama' => 'Anas Malik', 'kelas' => '1 Tsanawiyah', 'arah' => 'masuk', 'izin' => true, 'jam' => '09:20', 'ket' => 'Kembali dari izin' ],
                ['id' => 12, 'nama' => 'Abu Bakar', 'kelas' => '3 Aliyah', 'arah' => 'keluar', 'izin' => true, 'jam' => '08:15', 'ket' => 'Izin keperluan keluarga'],
            ],
        ]);
    }

    public function ronda(): Response
    {
        return Inertia::render('Keamanan/Ronda', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'titik' => [
                ['id' => 1, 'nama' => 'Pos Depan', 'jam' => '22:00', 'diperiksa' => true],
                ['id' => 2, 'nama' => 'Masjid', 'jam' => '22:15', 'diperiksa' => true],
                ['id' => 3, 'nama' => 'Asrama Putra A', 'jam' => '22:30', 'diperiksa' => false],
                ['id' => 4, 'nama' => 'Asrama Putri', 'jam' => '22:45', 'diperiksa' => false],
                ['id' => 5, 'nama' => 'Dapur', 'jam' => '23:00', 'diperiksa' => false],
                ['id' => 6, 'nama' => 'Gerbang Belakang', 'jam' => '23:15', 'diperiksa' => false],
            ],
        ]);
    }
}
