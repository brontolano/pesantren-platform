<?php

namespace App\Http\Controllers\DemoApps;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * App PWA demo "Humas Pesantren" — persona Staf Humas/PR.
 *
 * Seluruh data INLINE dummy (tak ada query DB/model) agar layar selalu penuh &
 * nol error 500 saat peragaan. Satu-satunya sentuhan runtime adalah nama user
 * untuk sapaan beranda. Aksi tulis (publikasi) ditangani optimistic di klien
 * via BottomSheet konfirmasi tanpa POST ke server.
 */
class HumasDemoController extends Controller
{
    /** Izin opsional (demo: selalu terbuka). Disediakan agar pola seragam. */
    private function can(): array
    {
        return [
            'kelola_berita' => true,
            'kelola_galeri' => true,
        ];
    }

    /**
     * Item bottom-nav (kontrak {key,label,href,active}).
     * Audit ikon → semua ada di whitelist: home, document, camera, calendar.
     */
    private function nav(): array
    {
        $items = [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => '/humas', 'icon' => 'home', 'route' => 'tenant.humas.beranda'],
            ['key' => 'berita', 'label' => 'Berita', 'href' => '/humas/berita', 'icon' => 'document', 'route' => 'tenant.humas.berita'],
            ['key' => 'galeri', 'label' => 'Galeri', 'href' => '/humas/galeri', 'icon' => 'camera', 'route' => 'tenant.humas.galeri'],
            ['key' => 'agenda', 'label' => 'Agenda', 'href' => '/humas/agenda', 'icon' => 'calendar', 'route' => 'tenant.humas.agenda'],
        ];

        return collect($items)
            ->map(fn (array $i): array => [
                'key' => $i['key'],
                'label' => $i['label'],
                'href' => $i['href'],
                'icon' => $i['icon'],
                'active' => request()->routeIs($i['route']),
            ])
            ->all();
    }

    /** Daftar artikel dummy dipakai bersama oleh layar Berita & DetailArtikel. */
    private function artikelSeed(): array
    {
        return [
            [
                'id' => 1,
                'judul' => 'Wisuda Tahfidz Angkatan ke-12 Berlangsung Khidmat',
                'kategori' => 'berita',
                'status' => 'published',
                'tanggal' => '12 Juni 2026',
                'penulis' => 'Tim Humas',
                'ringkasan' => 'Sebanyak 84 santri diwisuda setelah menuntaskan hafalan 30 juz Al-Qur\'an.',
                'isi' => 'Pondok Pesantren menggelar Wisuda Tahfidz Angkatan ke-12 di aula utama, Kamis pagi. Sebanyak 84 santri dinyatakan lulus setelah menuntaskan hafalan 30 juz Al-Qur\'an melalui proses munaqasyah yang ketat. Acara dihadiri para wali santri, masyayikh, serta perwakilan Kementerian Agama. Pimpinan pondok dalam sambutannya berpesan agar para hafiz menjaga hafalan dan mengamalkan kandungan Al-Qur\'an dalam keseharian.',
                'warna' => 'emerald',
                'inisial' => 'WT',
            ],
            [
                'id' => 2,
                'judul' => 'Pengumuman Libur Akhir Semester Genap 2026',
                'kategori' => 'pengumuman',
                'status' => 'published',
                'tanggal' => '10 Juni 2026',
                'penulis' => 'Bagian Pengasuhan',
                'ringkasan' => 'Santri diperkenankan pulang mulai 20 Juni dan kembali 6 Juli 2026.',
                'isi' => 'Diberitahukan kepada seluruh wali santri bahwa libur akhir semester genap berlangsung mulai Sabtu, 20 Juni 2026. Santri wajib kembali ke pondok paling lambat Senin, 6 Juli 2026 sebelum pukul 16.00 WIB. Penjemputan dilakukan melalui pintu gerbang utama dengan menunjukkan kartu wali. Mohon kerja samanya demi kelancaran proses kepulangan.',
                'warna' => 'blue',
                'inisial' => 'LB',
            ],
            [
                'id' => 3,
                'judul' => 'Santri Raih Juara 1 Olimpiade Sains Tingkat Provinsi',
                'kategori' => 'prestasi',
                'status' => 'published',
                'tanggal' => '8 Juni 2026',
                'penulis' => 'Tim Humas',
                'ringkasan' => 'Ahmad Fauzan (kelas XI) menyabet medali emas bidang Matematika.',
                'isi' => 'Kabar membanggakan datang dari ajang Olimpiade Sains Nasional tingkat provinsi. Ananda Ahmad Fauzan, santri kelas XI, berhasil meraih Juara 1 (medali emas) untuk bidang Matematika. Prestasi ini menjadi bukti bahwa keseimbangan antara ilmu agama dan ilmu umum dapat berjalan beriringan di lingkungan pesantren. Selamat dan barakallahu fiik!',
                'warna' => 'amber',
                'inisial' => 'JS',
            ],
            [
                'id' => 4,
                'judul' => 'Bakti Sosial Santri di Desa Sekitar Pondok',
                'kategori' => 'berita',
                'status' => 'draft',
                'tanggal' => '13 Juni 2026',
                'penulis' => 'Tim Humas',
                'ringkasan' => 'Naskah masih dalam penyuntingan, menunggu foto kegiatan.',
                'isi' => 'Draf artikel kegiatan bakti sosial santri yang membagikan sembako kepada warga kurang mampu di tiga desa sekitar pondok. Naskah masih dalam tahap penyuntingan dan menunggu dokumentasi foto dari panitia sebelum dipublikasikan.',
                'warna' => 'teal',
                'inisial' => 'BS',
            ],
            [
                'id' => 5,
                'judul' => 'Pelantikan Pengurus Organisasi Santri Periode Baru',
                'kategori' => 'pengumuman',
                'status' => 'draft',
                'tanggal' => '13 Juni 2026',
                'penulis' => 'Bagian Pengasuhan',
                'ringkasan' => 'Menunggu persetujuan redaksi sebelum terbit.',
                'isi' => 'Draf pengumuman pelantikan pengurus organisasi santri periode 2026/2027 yang akan dilaksanakan pekan depan. Susunan kepengurusan masih difinalisasi dan akan diumumkan setelah disetujui pimpinan.',
                'warna' => 'slate',
                'inisial' => 'PO',
            ],
        ];
    }

    public function beranda()
    {
        return Inertia::render('Humas/Beranda', [
            'userName' => Auth::user()?->name,
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'stats' => [
                ['key' => 'publik', 'label' => 'Berita Publik', 'value' => 24, 'icon' => 'document', 'tone' => 'emerald', 'hint' => 'Sudah terbit'],
                ['key' => 'draft', 'label' => 'Draf', 'value' => 5, 'icon' => 'clock', 'tone' => 'amber', 'hint' => 'Belum terbit'],
                ['key' => 'foto', 'label' => 'Foto Galeri', 'value' => 312, 'icon' => 'camera', 'tone' => 'blue', 'hint' => '18 album'],
                ['key' => 'agenda', 'label' => 'Agenda', 'value' => 6, 'icon' => 'calendar', 'tone' => 'teal', 'hint' => 'Mendatang'],
            ],
            'terbitTerbaru' => [
                ['id' => 1, 'judul' => 'Wisuda Tahfidz Angkatan ke-12 Berlangsung Khidmat', 'kategori' => 'Berita', 'tanggal' => '12 Juni 2026', 'inisial' => 'WT', 'warna' => 'emerald'],
                ['id' => 3, 'judul' => 'Santri Raih Juara 1 Olimpiade Sains Provinsi', 'kategori' => 'Prestasi', 'tanggal' => '8 Juni 2026', 'inisial' => 'JS', 'warna' => 'amber'],
                ['id' => 2, 'judul' => 'Pengumuman Libur Akhir Semester Genap 2026', 'kategori' => 'Pengumuman', 'tanggal' => '10 Juni 2026', 'inisial' => 'LB', 'warna' => 'blue'],
            ],
        ]);
    }

    public function berita()
    {
        return Inertia::render('Humas/Berita', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'artikel' => $this->artikelSeed(),
        ]);
    }

    public function detailArtikel(int $artikel)
    {
        $semua = collect($this->artikelSeed());
        $data = $semua->firstWhere('id', $artikel) ?? $semua->first();

        return Inertia::render('Humas/DetailArtikel', [
            'navigation' => $this->nav(),
            'artikel' => $data,
        ]);
    }

    public function galeri()
    {
        return Inertia::render('Humas/Galeri', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'album' => [
                [
                    'id' => 1, 'judul' => 'Wisuda Tahfidz ke-12', 'jumlah' => 48, 'tanggal' => '12 Jun 2026', 'warna' => 'emerald', 'inisial' => 'WT',
                    'foto' => [
                        ['id' => 1, 'caption' => 'Pembukaan acara', 'warna' => 'emerald'],
                        ['id' => 2, 'caption' => 'Penyerahan syahadah', 'warna' => 'teal'],
                        ['id' => 3, 'caption' => 'Sambutan pimpinan', 'warna' => 'amber'],
                        ['id' => 4, 'caption' => 'Foto bersama wali', 'warna' => 'blue'],
                        ['id' => 5, 'caption' => 'Doa penutup', 'warna' => 'rose'],
                        ['id' => 6, 'caption' => 'Ramah tamah', 'warna' => 'slate'],
                    ],
                ],
                [
                    'id' => 2, 'judul' => 'Olimpiade Sains Provinsi', 'jumlah' => 22, 'tanggal' => '8 Jun 2026', 'warna' => 'amber', 'inisial' => 'OS',
                    'foto' => [
                        ['id' => 1, 'caption' => 'Persiapan lomba', 'warna' => 'amber'],
                        ['id' => 2, 'caption' => 'Sesi pengerjaan', 'warna' => 'blue'],
                        ['id' => 3, 'caption' => 'Pengumuman juara', 'warna' => 'emerald'],
                        ['id' => 4, 'caption' => 'Penyerahan medali', 'warna' => 'rose'],
                    ],
                ],
                [
                    'id' => 3, 'judul' => 'Pekan Olahraga Santri', 'jumlah' => 64, 'tanggal' => '1 Jun 2026', 'warna' => 'blue', 'inisial' => 'PO',
                    'foto' => [
                        ['id' => 1, 'caption' => 'Upacara pembukaan', 'warna' => 'blue'],
                        ['id' => 2, 'caption' => 'Pertandingan futsal', 'warna' => 'emerald'],
                        ['id' => 3, 'caption' => 'Lomba lari estafet', 'warna' => 'amber'],
                        ['id' => 4, 'caption' => 'Tarik tambang', 'warna' => 'teal'],
                    ],
                ],
                [
                    'id' => 4, 'judul' => 'Haul Akbar & Khataman', 'jumlah' => 37, 'tanggal' => '24 Mei 2026', 'warna' => 'teal', 'inisial' => 'HA',
                    'foto' => [
                        ['id' => 1, 'caption' => 'Pembacaan tahlil', 'warna' => 'teal'],
                        ['id' => 2, 'caption' => 'Mauidzah hasanah', 'warna' => 'emerald'],
                        ['id' => 3, 'caption' => 'Jamuan tamu', 'warna' => 'amber'],
                    ],
                ],
            ],
        ]);
    }

    public function agenda()
    {
        return Inertia::render('Humas/Agenda', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'agenda' => [
                ['id' => 1, 'judul' => 'Rapat Koordinasi Panitia Liburan', 'jenis' => 'Rapat', 'tanggal' => 'Senin, 16 Juni 2026', 'waktu' => '09.00 WIB', 'lokasi' => 'Ruang Rapat Utama', 'warna' => 'blue'],
                ['id' => 2, 'judul' => 'Liputan Penutupan Semester Genap', 'jenis' => 'Liputan', 'tanggal' => 'Jumat, 20 Juni 2026', 'waktu' => '08.00 WIB', 'lokasi' => 'Aula Pondok', 'warna' => 'emerald'],
                ['id' => 3, 'judul' => 'Penjemputan Santri Liburan', 'jenis' => 'Kegiatan', 'tanggal' => 'Sabtu, 20 Juni 2026', 'waktu' => '13.00 WIB', 'lokasi' => 'Gerbang Utama', 'warna' => 'amber'],
                ['id' => 4, 'judul' => 'Update Konten Website & Medsos', 'jenis' => 'Publikasi', 'tanggal' => 'Senin, 23 Juni 2026', 'waktu' => '10.00 WIB', 'lokasi' => 'Kantor Humas', 'warna' => 'teal'],
                ['id' => 5, 'judul' => 'Liputan Kedatangan Santri Baru', 'jenis' => 'Liputan', 'tanggal' => 'Senin, 6 Juli 2026', 'waktu' => '14.00 WIB', 'lokasi' => 'Gerbang Utama', 'warna' => 'emerald'],
                ['id' => 6, 'judul' => 'Konferensi Pers Tahun Ajaran Baru', 'jenis' => 'Rapat', 'tanggal' => 'Rabu, 8 Juli 2026', 'waktu' => '09.30 WIB', 'lokasi' => 'Ruang Pimpinan', 'warna' => 'blue'],
            ],
        ]);
    }
}
