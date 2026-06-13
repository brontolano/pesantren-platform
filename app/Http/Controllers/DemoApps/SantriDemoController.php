<?php

namespace App\Http\Controllers\DemoApps;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Demo PWA "Aplikasi Santri" (Vue/Inertia) — persona santri yang boleh bawa HP.
 *
 * DEMO-GRADE: seluruh data adalah dummy inline (array PHP) — TIDAK ada query
 * DB/model apa pun kecuali Auth::user()?->name untuk sapaan. Ini menjamin nol
 * error 500 & layar selalu terisi penuh saat peragaan. Aksi tulis di sisi Vue
 * bersifat optimistic (BottomSheet konfirmasi, tanpa POST).
 *
 * Gate: middleware('auth') saja — tanpa permission baru.
 */
class SantriDemoController extends Controller
{
    /**
     * Item bottom-nav (kontrak {key,label,href,active,icon}). Aktif via nama
     * rute tenant.santri-app.<x>. Ikon tiap key sudah diaudit ada di whitelist
     * UiIcon: home / calendar / book / wallet / bell.
     */
    private function nav(): array
    {
        $base = '/santri-app';
        $items = [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => $base, 'route' => 'beranda', 'icon' => 'home'],
            ['key' => 'jadwal', 'label' => 'Jadwal', 'href' => "{$base}/jadwal", 'route' => 'jadwal', 'icon' => 'calendar'],
            ['key' => 'hafalan', 'label' => 'Hafalan', 'href' => "{$base}/hafalan", 'route' => 'hafalan', 'icon' => 'book'],
            ['key' => 'keuangan', 'label' => 'Keuangan', 'href' => "{$base}/keuangan", 'route' => 'keuangan', 'icon' => 'wallet'],
            ['key' => 'info', 'label' => 'Info', 'href' => "{$base}/pengumuman", 'route' => 'pengumuman', 'icon' => 'bell'],
        ];

        return collect($items)
            ->map(fn (array $i): array => [
                'key' => $i['key'],
                'label' => $i['label'],
                'href' => $i['href'],
                'icon' => $i['icon'],
                'active' => request()->routeIs("tenant.santri-app.{$i['route']}"),
            ])
            ->all();
    }

    public function beranda()
    {
        return Inertia::render('SantriApp/Beranda', [
            'navigation' => $this->nav(),
            'userName' => Auth::user()?->name ?? 'Santri Demo',
            'stats' => [
                ['key' => 'tabungan', 'label' => 'Saldo Tabungan', 'value' => 'Rp750.000', 'hint' => 'koperasi santri', 'icon' => 'wallet', 'tone' => 'emerald'],
                ['key' => 'spp', 'label' => 'Tagihan SPP', 'value' => 'Rp350.000', 'hint' => 'jatuh tempo 20 Jun', 'icon' => 'receipt', 'tone' => 'rose'],
                ['key' => 'hafalan', 'label' => 'Hafalan', 'value' => '5 juz', 'hint' => 'target 10 juz', 'icon' => 'book', 'tone' => 'teal'],
                ['key' => 'ujian', 'label' => 'Ujian Aktif', 'value' => '2', 'hint' => 'minggu ini', 'icon' => 'chart', 'tone' => 'blue'],
            ],
            'jadwalHariIni' => [
                ['id' => 1, 'jam' => '07:30', 'pelajaran' => 'Tahfizh Al-Qur\'an', 'pengajar' => 'Ust. Abdullah', 'ruang' => 'Aula 1'],
                ['id' => 2, 'jam' => '09:00', 'pelajaran' => 'Bahasa Arab', 'pengajar' => 'Ust. Hasan', 'ruang' => 'Kelas 8A'],
                ['id' => 3, 'jam' => '10:30', 'pelajaran' => 'Fiqih', 'pengajar' => 'Ust. Mahmud', 'ruang' => 'Kelas 8A'],
                ['id' => 4, 'jam' => '13:00', 'pelajaran' => 'Matematika', 'pengajar' => 'Ust. Rahmat', 'ruang' => 'Kelas 8A'],
            ],
            'pengumuman' => [
                ['id' => 1, 'judul' => 'Libur Akhir Pekan Ditiadakan', 'ringkas' => 'Persiapan ujian tengah semester pekan depan.', 'tanggal' => '13 Jun 2026'],
                ['id' => 2, 'judul' => 'Jadwal Setoran Hafalan Baru', 'ringkas' => 'Setoran ba\'da Subuh dan ba\'da Maghrib mulai Senin.', 'tanggal' => '12 Jun 2026'],
                ['id' => 3, 'judul' => 'Pembayaran SPP Bulan Juni', 'ringkas' => 'Mohon lunasi sebelum tanggal 20 Juni.', 'tanggal' => '10 Jun 2026'],
            ],
            'domainTiles' => [
                ['key' => 'jadwal', 'label' => 'Jadwal', 'href' => '/santri-app/jadwal', 'icon' => 'calendar', 'tone' => 'blue'],
                ['key' => 'hafalan', 'label' => 'Hafalan', 'href' => '/santri-app/hafalan', 'icon' => 'book', 'tone' => 'teal'],
                ['key' => 'keuangan', 'label' => 'Keuangan', 'href' => '/santri-app/keuangan', 'icon' => 'wallet', 'tone' => 'emerald'],
                ['key' => 'izin', 'label' => 'Izin', 'href' => '/santri-app/izin', 'icon' => 'document', 'tone' => 'amber'],
                ['key' => 'info', 'label' => 'Pengumuman', 'href' => '/santri-app/pengumuman', 'icon' => 'bell', 'tone' => 'rose'],
            ],
        ]);
    }

    public function jadwal()
    {
        return Inertia::render('SantriApp/Jadwal', [
            'navigation' => $this->nav(),
            'hari' => [
                ['value' => 'senin', 'label' => 'Senin'],
                ['value' => 'selasa', 'label' => 'Selasa'],
                ['value' => 'rabu', 'label' => 'Rabu'],
                ['value' => 'kamis', 'label' => 'Kamis'],
                ['value' => 'jumat', 'label' => 'Jumat'],
            ],
            'jadwal' => [
                'senin' => [
                    ['id' => 1, 'jam' => '07:30', 'pelajaran' => 'Tahfizh Al-Qur\'an', 'pengajar' => 'Ust. Abdullah', 'ruang' => 'Aula 1'],
                    ['id' => 2, 'jam' => '09:00', 'pelajaran' => 'Bahasa Arab', 'pengajar' => 'Ust. Hasan', 'ruang' => 'Kelas 8A'],
                    ['id' => 3, 'jam' => '10:30', 'pelajaran' => 'Fiqih', 'pengajar' => 'Ust. Mahmud', 'ruang' => 'Kelas 8A'],
                    ['id' => 4, 'jam' => '13:00', 'pelajaran' => 'Matematika', 'pengajar' => 'Ust. Rahmat', 'ruang' => 'Kelas 8A'],
                ],
                'selasa' => [
                    ['id' => 1, 'jam' => '07:30', 'pelajaran' => 'Tahfizh Al-Qur\'an', 'pengajar' => 'Ust. Abdullah', 'ruang' => 'Aula 1'],
                    ['id' => 2, 'jam' => '09:00', 'pelajaran' => 'Hadits', 'pengajar' => 'Ust. Salim', 'ruang' => 'Kelas 8A'],
                    ['id' => 3, 'jam' => '10:30', 'pelajaran' => 'IPA Terpadu', 'pengajar' => 'Ust. Yahya', 'ruang' => 'Lab IPA'],
                    ['id' => 4, 'jam' => '13:00', 'pelajaran' => 'Bahasa Inggris', 'pengajar' => 'Ust. Karim', 'ruang' => 'Kelas 8A'],
                ],
                'rabu' => [
                    ['id' => 1, 'jam' => '07:30', 'pelajaran' => 'Tahfizh Al-Qur\'an', 'pengajar' => 'Ust. Abdullah', 'ruang' => 'Aula 1'],
                    ['id' => 2, 'jam' => '09:00', 'pelajaran' => 'Nahwu Sharaf', 'pengajar' => 'Ust. Hasan', 'ruang' => 'Kelas 8A'],
                    ['id' => 3, 'jam' => '10:30', 'pelajaran' => 'Akidah Akhlak', 'pengajar' => 'Ust. Mahmud', 'ruang' => 'Kelas 8A'],
                    ['id' => 4, 'jam' => '13:00', 'pelajaran' => 'IPS Terpadu', 'pengajar' => 'Ust. Faisal', 'ruang' => 'Kelas 8A'],
                ],
                'kamis' => [
                    ['id' => 1, 'jam' => '07:30', 'pelajaran' => 'Tahfizh Al-Qur\'an', 'pengajar' => 'Ust. Abdullah', 'ruang' => 'Aula 1'],
                    ['id' => 2, 'jam' => '09:00', 'pelajaran' => 'Tafsir', 'pengajar' => 'Ust. Salim', 'ruang' => 'Kelas 8A'],
                    ['id' => 3, 'jam' => '10:30', 'pelajaran' => 'Matematika', 'pengajar' => 'Ust. Rahmat', 'ruang' => 'Kelas 8A'],
                    ['id' => 4, 'jam' => '13:00', 'pelajaran' => 'Bahasa Indonesia', 'pengajar' => 'Ust. Karim', 'ruang' => 'Kelas 8A'],
                ],
                'jumat' => [
                    ['id' => 1, 'jam' => '07:30', 'pelajaran' => 'Tahfizh Al-Qur\'an', 'pengajar' => 'Ust. Abdullah', 'ruang' => 'Aula 1'],
                    ['id' => 2, 'jam' => '09:00', 'pelajaran' => 'Khat & Imla', 'pengajar' => 'Ust. Hasan', 'ruang' => 'Kelas 8A'],
                    ['id' => 3, 'jam' => '10:00', 'pelajaran' => 'Persiapan Jum\'at', 'pengajar' => 'Ust. Mahmud', 'ruang' => 'Masjid'],
                ],
            ],
        ]);
    }

    public function hafalan()
    {
        return Inertia::render('SantriApp/Hafalan', [
            'navigation' => $this->nav(),
            'progress' => [
                'juz' => 5,
                'target' => 10,
                'halaman' => 102,
                'ayatPekanIni' => 24,
            ],
            'setoran' => [
                ['id' => 1, 'surah' => 'Al-Baqarah 1-20', 'juz' => 1, 'nilai' => 'mumtaz', 'tanggal' => '13 Jun 2026'],
                ['id' => 2, 'surah' => 'Ali Imran 1-15', 'juz' => 3, 'nilai' => 'jayyid_jiddan', 'tanggal' => '12 Jun 2026'],
                ['id' => 3, 'surah' => 'An-Nisa 1-10', 'juz' => 4, 'nilai' => 'jayyid', 'tanggal' => '11 Jun 2026'],
                ['id' => 4, 'surah' => 'Al-Maidah 1-12', 'juz' => 6, 'nilai' => 'mumtaz', 'tanggal' => '10 Jun 2026'],
                ['id' => 5, 'surah' => 'Al-An\'am 1-8', 'juz' => 7, 'nilai' => 'jayyid_jiddan', 'tanggal' => '09 Jun 2026'],
            ],
        ]);
    }

    public function keuangan()
    {
        return Inertia::render('SantriApp/Keuangan', [
            'navigation' => $this->nav(),
            'saldo' => 750000,
            'tagihan' => [
                ['id' => 1, 'nama' => 'SPP Juni 2026', 'jumlah' => 350000, 'status' => 'belum_bayar', 'jatuhTempo' => '20 Jun 2026'],
                ['id' => 2, 'nama' => 'Uang Makan Juni', 'jumlah' => 450000, 'status' => 'lunas', 'jatuhTempo' => '05 Jun 2026'],
                ['id' => 3, 'nama' => 'Kegiatan Rihlah', 'jumlah' => 150000, 'status' => 'belum_bayar', 'jatuhTempo' => '25 Jun 2026'],
            ],
            'mutasi' => [
                ['id' => 1, 'keterangan' => 'Setoran dari Wali', 'jumlah' => 500000, 'arah' => 'masuk', 'tanggal' => '12 Jun 2026'],
                ['id' => 2, 'keterangan' => 'Jajan Koperasi', 'jumlah' => 25000, 'arah' => 'keluar', 'tanggal' => '12 Jun 2026'],
                ['id' => 3, 'keterangan' => 'Beli Alat Tulis', 'jumlah' => 35000, 'arah' => 'keluar', 'tanggal' => '11 Jun 2026'],
                ['id' => 4, 'keterangan' => 'Setoran dari Wali', 'jumlah' => 300000, 'arah' => 'masuk', 'tanggal' => '08 Jun 2026'],
                ['id' => 5, 'keterangan' => 'Laundry', 'jumlah' => 20000, 'arah' => 'keluar', 'tanggal' => '07 Jun 2026'],
            ],
        ]);
    }

    public function izin()
    {
        return Inertia::render('SantriApp/Izin', [
            'navigation' => $this->nav(),
            'jenisOptions' => [
                ['value' => 'pulang', 'label' => 'Pulang / Sakan'],
                ['value' => 'sakit', 'label' => 'Sakit'],
                ['value' => 'keperluan', 'label' => 'Keperluan Keluarga'],
                ['value' => 'lainnya', 'label' => 'Lainnya'],
            ],
            'riwayat' => [
                ['id' => 1, 'jenis' => 'Pulang / Sakan', 'alasan' => 'Acara keluarga di rumah', 'status' => 'terverifikasi', 'tanggal' => '08 Jun 2026'],
                ['id' => 2, 'jenis' => 'Sakit', 'alasan' => 'Demam, istirahat di UKS', 'status' => 'terverifikasi', 'tanggal' => '05 Jun 2026'],
                ['id' => 3, 'jenis' => 'Keperluan Keluarga', 'alasan' => 'Menjenguk kakek sakit', 'status' => 'menunggu', 'tanggal' => '13 Jun 2026'],
                ['id' => 4, 'jenis' => 'Lainnya', 'alasan' => 'Kontrol gigi ke dokter', 'status' => 'ditolak', 'tanggal' => '02 Jun 2026'],
            ],
        ]);
    }

    public function pengumuman()
    {
        return Inertia::render('SantriApp/Pengumuman', [
            'navigation' => $this->nav(),
            'pengumuman' => [
                ['id' => 1, 'judul' => 'Libur Akhir Pekan Ditiadakan', 'isi' => 'Seluruh santri tetap di pondok pekan ini untuk persiapan ujian tengah semester. Mohon tidak mengajukan izin pulang kecuali darurat.', 'tanggal' => '13 Jun 2026', 'kategori' => 'Akademik'],
                ['id' => 2, 'judul' => 'Jadwal Setoran Hafalan Baru', 'isi' => 'Mulai Senin, setoran hafalan dilakukan ba\'da Subuh dan ba\'da Maghrib. Harap menyiapkan target masing-masing.', 'tanggal' => '12 Jun 2026', 'kategori' => 'Tahfizh'],
                ['id' => 3, 'judul' => 'Pembayaran SPP Bulan Juni', 'isi' => 'Mohon menginformasikan kepada wali untuk melunasi SPP Juni sebelum tanggal 20. Cek tagihan di menu Keuangan.', 'tanggal' => '10 Jun 2026', 'kategori' => 'Keuangan'],
            ],
            'ujian' => [
                ['id' => 1, 'mapel' => 'Bahasa Arab', 'jenis' => 'UTS Ganjil', 'waktu' => '16 Jun 2026 • 08:00', 'durasi' => '90 menit', 'status' => 'berlangsung'],
                ['id' => 2, 'mapel' => 'Fiqih', 'jenis' => 'UTS Ganjil', 'waktu' => '17 Jun 2026 • 08:00', 'durasi' => '90 menit', 'status' => 'berlangsung'],
                ['id' => 3, 'mapel' => 'Tahfizh', 'jenis' => 'Ujian Setoran', 'waktu' => '10 Jun 2026 • 08:00', 'durasi' => '60 menit', 'status' => 'selesai'],
                ['id' => 4, 'mapel' => 'Matematika', 'jenis' => 'Kuis Harian', 'waktu' => '09 Jun 2026 • 13:00', 'durasi' => '45 menit', 'status' => 'selesai'],
            ],
        ]);
    }
}
