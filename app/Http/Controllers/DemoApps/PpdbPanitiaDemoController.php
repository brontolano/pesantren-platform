<?php

declare(strict_types=1);

namespace App\Http\Controllers\DemoApps;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * App PWA DEMO "PPDB Panitia" — peragaan alur panitia Penerimaan Peserta Didik
 * Baru (verifikasi berkas → keputusan kelulusan) dari HP.
 *
 * SIFAT: 100% data DUMMY inline (tidak ada query DB/model). Setiap layar = satu
 * Inertia::render('PpdbPanitia/<Page>', [...]). Tujuan: showcase UI yang selalu
 * penuh & nol risiko 500. Satu-satunya sentuhan runtime = Auth::user()?->name
 * untuk sapaan. Aksi tulis (verifikasi/keputusan) bersifat optimistic di sisi
 * Vue (tidak mengirim POST / tidak mengubah data).
 *
 * Gate: middleware('auth') saja — TANPA permission baru (lihat routeSnippet).
 */
class PpdbPanitiaDemoController extends Controller
{
    /** Daftar dummy ~15 calon santri (dipakai lintas layar: list, detail, dst). */
    private function pendaftarDummy(): array
    {
        return [
            ['id' => 1, 'no' => 'PPDB-2026-001', 'nama' => 'Ahmad Fauzan Hakim', 'program' => 'Tahfizh', 'gelombang' => 1, 'status' => 'lunas', 'asal' => 'MTs Al-Hidayah Bandung', 'skor' => 88],
            ['id' => 2, 'no' => 'PPDB-2026-002', 'nama' => 'Siti Khadijah Nuraini', 'program' => 'Kitab Kuning', 'gelombang' => 1, 'status' => 'menunggu', 'asal' => 'SMP Negeri 3 Sumedang', 'skor' => 79],
            ['id' => 3, 'no' => 'PPDB-2026-003', 'nama' => 'Muhammad Rizki Pratama', 'program' => 'Tahfizh', 'gelombang' => 1, 'status' => 'lunas', 'asal' => 'MTs Persis Tarogong', 'skor' => 91],
            ['id' => 4, 'no' => 'PPDB-2026-004', 'nama' => 'Nabila Putri Ramadhani', 'program' => 'Sains Terpadu', 'gelombang' => 1, 'status' => 'ditolak', 'asal' => 'SMP IT Insan Kamil', 'skor' => 62],
            ['id' => 5, 'no' => 'PPDB-2026-005', 'nama' => 'Abdullah Zaki Maulana', 'program' => 'Kitab Kuning', 'gelombang' => 1, 'status' => 'menunggu', 'asal' => 'MTs Darul Arqam', 'skor' => 74],
            ['id' => 6, 'no' => 'PPDB-2026-006', 'nama' => 'Fatimah Az-Zahra', 'program' => 'Tahfizh', 'gelombang' => 2, 'status' => 'lunas', 'asal' => 'SMP Muhammadiyah 1', 'skor' => 95],
            ['id' => 7, 'no' => 'PPDB-2026-007', 'nama' => 'Yusuf Ibrahim Saputra', 'program' => 'Sains Terpadu', 'gelombang' => 2, 'status' => 'menunggu', 'asal' => 'MTs Al-Falah Cicalengka', 'skor' => 83],
            ['id' => 8, 'no' => 'PPDB-2026-008', 'nama' => 'Aisyah Salsabila', 'program' => 'Kitab Kuning', 'gelombang' => 2, 'status' => 'lunas', 'asal' => 'SMP Negeri 1 Tanjungsari', 'skor' => 80],
            ['id' => 9, 'no' => 'PPDB-2026-009', 'nama' => 'Hamzah Abdurrahman', 'program' => 'Tahfizh', 'gelombang' => 2, 'status' => 'menunggu', 'asal' => 'MTs Al-Ihsan', 'skor' => 77],
            ['id' => 10, 'no' => 'PPDB-2026-010', 'nama' => 'Maryam Hanifah', 'program' => 'Sains Terpadu', 'gelombang' => 2, 'status' => 'ditolak', 'asal' => 'SMP IT Baiturrahman', 'skor' => 58],
            ['id' => 11, 'no' => 'PPDB-2026-011', 'nama' => 'Bilal Ramadhan', 'program' => 'Kitab Kuning', 'gelombang' => 2, 'status' => 'lunas', 'asal' => 'MTs Nurul Huda', 'skor' => 85],
            ['id' => 12, 'no' => 'PPDB-2026-012', 'nama' => 'Khalisah Dewi Anjani', 'program' => 'Tahfizh', 'gelombang' => 3, 'status' => 'menunggu', 'asal' => 'SMP Negeri 2 Jatinangor', 'skor' => 72],
            ['id' => 13, 'no' => 'PPDB-2026-013', 'nama' => 'Umar Faruq Sidiq', 'program' => 'Sains Terpadu', 'gelombang' => 3, 'status' => 'menunggu', 'asal' => 'MTs Al-Barokah', 'skor' => 81],
            ['id' => 14, 'no' => 'PPDB-2026-014', 'nama' => 'Zahratus Sita', 'program' => 'Kitab Kuning', 'gelombang' => 3, 'status' => 'lunas', 'asal' => 'SMP Plus Babussalam', 'skor' => 89],
            ['id' => 15, 'no' => 'PPDB-2026-015', 'nama' => 'Ali Akbar Maulana', 'program' => 'Tahfizh', 'gelombang' => 3, 'status' => 'menunggu', 'asal' => 'MTs Riyadhul Jannah', 'skor' => 76],
        ];
    }

    public function beranda(): Response
    {
        return Inertia::render('PpdbPanitia/Beranda', [
            'userName' => Auth::user()?->name,
            'gelombang' => [
                ['value' => 1, 'label' => 'Gel. 1'],
                ['value' => 2, 'label' => 'Gel. 2'],
                ['value' => 3, 'label' => 'Gel. 3'],
            ],
            'statistik' => [
                1 => ['total' => 42, 'menunggu' => 11, 'lulus' => 24, 'ditolak' => 7],
                2 => ['total' => 38, 'menunggu' => 15, 'lulus' => 18, 'ditolak' => 5],
                3 => ['total' => 19, 'menunggu' => 13, 'lulus' => 4, 'ditolak' => 2],
            ],
            'agenda' => [
                ['label' => 'Verifikasi berkas Gel. 3', 'tanggal' => '14 Jun 2026', 'status' => 'menunggu'],
                ['label' => 'Pengumuman kelulusan Gel. 2', 'tanggal' => '16 Jun 2026', 'status' => 'info'],
                ['label' => 'Daftar ulang Gel. 1', 'tanggal' => '20 Jun 2026', 'status' => 'aktif'],
            ],
            'navigation' => $this->nav(),
        ]);
    }

    public function pendaftar(): Response
    {
        return Inertia::render('PpdbPanitia/Pendaftar', [
            'pendaftar' => $this->pendaftarDummy(),
            'navigation' => $this->nav(),
        ]);
    }

    public function detail(): Response
    {
        return Inertia::render('PpdbPanitia/Detail', [
            'calon' => [
                'id' => 6,
                'no' => 'PPDB-2026-006',
                'nama' => 'Fatimah Az-Zahra',
                'status' => 'lunas',
                'program' => 'Tahfizh',
                'gelombang' => 2,
                'token' => 'TRK-9F3A21',
                'biodata' => [
                    ['label' => 'Tanggal Lahir', 'value' => '12 Maret 2012'],
                    ['label' => 'Jenis Kelamin', 'value' => 'Perempuan'],
                    ['label' => 'Asal Sekolah', 'value' => 'SMP Muhammadiyah 1'],
                    ['label' => 'Nama Ayah', 'value' => 'H. Imron Rosyadi'],
                    ['label' => 'Nama Ibu', 'value' => 'Hj. Lailatul Badriyah'],
                    ['label' => 'No. HP Wali', 'value' => '0812-3456-7890'],
                    ['label' => 'Alamat', 'value' => 'Jl. Mawar No. 17, Sumedang'],
                ],
                'ujian' => [
                    ['label' => 'Baca Al-Qur\'an', 'nilai' => 92, 'maks' => 100],
                    ['label' => 'Hafalan Juz 30', 'nilai' => 96, 'maks' => 100],
                    ['label' => 'Wawancara', 'nilai' => 90, 'maks' => 100],
                    ['label' => 'Tes Tulis', 'nilai' => 88, 'maks' => 100],
                ],
                'dokumen' => [
                    ['nama' => 'Akta Kelahiran', 'status' => 'terverifikasi'],
                    ['nama' => 'Kartu Keluarga', 'status' => 'terverifikasi'],
                    ['nama' => 'Ijazah / SKL', 'status' => 'terverifikasi'],
                    ['nama' => 'Pas Foto', 'status' => 'terverifikasi'],
                    ['nama' => 'Rapor 2 Semester', 'status' => 'menunggu'],
                ],
            ],
            'navigation' => $this->nav(),
        ]);
    }

    public function verifikasi(): Response
    {
        return Inertia::render('PpdbPanitia/Verifikasi', [
            'calon' => [
                'no' => 'PPDB-2026-009',
                'nama' => 'Hamzah Abdurrahman',
                'program' => 'Tahfizh',
                'gelombang' => 2,
            ],
            'dokumen' => [
                ['id' => 1, 'nama' => 'Akta Kelahiran', 'berkas' => 'akta-hamzah.pdf', 'status' => 'terverifikasi'],
                ['id' => 2, 'nama' => 'Kartu Keluarga', 'berkas' => 'kk-hamzah.pdf', 'status' => 'terverifikasi'],
                ['id' => 3, 'nama' => 'Ijazah / SKL', 'berkas' => 'ijazah-hamzah.jpg', 'status' => 'menunggu'],
                ['id' => 4, 'nama' => 'Pas Foto', 'berkas' => 'foto-hamzah.jpg', 'status' => 'menunggu'],
                ['id' => 5, 'nama' => 'Rapor 2 Semester', 'berkas' => 'rapor-hamzah.pdf', 'status' => 'ditolak'],
            ],
            'navigation' => $this->nav(),
        ]);
    }

    public function keputusan(): Response
    {
        return Inertia::render('PpdbPanitia/Keputusan', [
            'calon' => [
                'no' => 'PPDB-2026-007',
                'nama' => 'Yusuf Ibrahim Saputra',
                'program' => 'Sains Terpadu',
                'gelombang' => 2,
                'asal' => 'MTs Al-Falah Cicalengka',
                'dokumenLengkap' => true,
                'ujian' => [
                    ['label' => 'Tes Tulis', 'nilai' => 85],
                    ['label' => 'Wawancara', 'nilai' => 82],
                    ['label' => 'Baca Al-Qur\'an', 'nilai' => 80],
                ],
                'rataRata' => 82,
            ],
            'opsiKeputusan' => [
                ['value' => 'lulus', 'label' => 'Lulus', 'tone' => 'emerald'],
                ['value' => 'cadangan', 'label' => 'Cadangan', 'tone' => 'amber'],
                ['value' => 'ditolak', 'label' => 'Ditolak', 'tone' => 'rose'],
            ],
            'navigation' => $this->nav(),
        ]);
    }

    public function hasil(): Response
    {
        return Inertia::render('PpdbPanitia/Hasil', [
            'hasil' => [
                ['id' => 1, 'no' => 'PPDB-2026-006', 'nama' => 'Fatimah Az-Zahra', 'program' => 'Tahfizh', 'keputusan' => 'lulus', 'token' => 'TRK-9F3A21', 'tanggal' => '10 Jun 2026'],
                ['id' => 2, 'no' => 'PPDB-2026-003', 'nama' => 'Muhammad Rizki Pratama', 'program' => 'Tahfizh', 'keputusan' => 'lulus', 'token' => 'TRK-3C77B1', 'tanggal' => '10 Jun 2026'],
                ['id' => 3, 'no' => 'PPDB-2026-011', 'nama' => 'Bilal Ramadhan', 'program' => 'Kitab Kuning', 'keputusan' => 'lulus', 'token' => 'TRK-5D02E9', 'tanggal' => '09 Jun 2026'],
                ['id' => 4, 'no' => 'PPDB-2026-013', 'nama' => 'Umar Faruq Sidiq', 'program' => 'Sains Terpadu', 'keputusan' => 'cadangan', 'token' => 'TRK-7A18F4', 'tanggal' => '09 Jun 2026'],
                ['id' => 5, 'no' => 'PPDB-2026-004', 'nama' => 'Nabila Putri Ramadhani', 'program' => 'Sains Terpadu', 'keputusan' => 'ditolak', 'token' => 'TRK-2B65C0', 'tanggal' => '08 Jun 2026'],
                ['id' => 6, 'no' => 'PPDB-2026-010', 'nama' => 'Maryam Hanifah', 'program' => 'Sains Terpadu', 'keputusan' => 'ditolak', 'token' => 'TRK-4E91A7', 'tanggal' => '08 Jun 2026'],
                ['id' => 7, 'no' => 'PPDB-2026-014', 'nama' => 'Zahratus Sita', 'program' => 'Kitab Kuning', 'keputusan' => 'lulus', 'token' => 'TRK-8F44D2', 'tanggal' => '07 Jun 2026'],
            ],
            'navigation' => $this->nav(),
        ]);
    }

    /**
     * Item bottom-nav (kontrak {key,label,href,active}).
     * 4 tab utama; layar Detail & Keputusan adalah sub-layar (bukan tab).
     * Ikon dipetakan di Vue (navIcons) — semua dari whitelist UiIcon:
     *   beranda=chart, pendaftar=user, verifikasi=check-circle, hasil=document.
     */
    private function nav(): array
    {
        $items = [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => '/ppdb-panitia', 'route' => 'beranda'],
            ['key' => 'pendaftar', 'label' => 'Pendaftar', 'href' => '/ppdb-panitia/pendaftar', 'route' => 'pendaftar'],
            ['key' => 'verifikasi', 'label' => 'Verifikasi', 'href' => '/ppdb-panitia/verifikasi', 'route' => 'verifikasi'],
            ['key' => 'hasil', 'label' => 'Hasil', 'href' => '/ppdb-panitia/hasil', 'route' => 'hasil'],
        ];

        return array_map(static fn (array $i): array => [
            'key' => $i['key'],
            'label' => $i['label'],
            'href' => $i['href'],
            'active' => request()->routeIs("tenant.ppdb-panitia.{$i['route']}"),
        ], $items);
    }
}
