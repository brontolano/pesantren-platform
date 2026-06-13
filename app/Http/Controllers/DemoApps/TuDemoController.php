<?php

declare(strict_types=1);

namespace App\Http\Controllers\DemoApps;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TuDemoController — PWA demo "Tata Usaha" (peragaan persona Staf TU).
 *
 * SEMUA layar memakai data DUMMY INLINE (array PHP) — TIDAK ADA query DB/model
 * apa pun kecuali Auth::user()?->name untuk sapaan. Tujuannya: showcase yang
 * selalu tampil penuh & nol 500 di tenant demo, tanpa bergantung skema modul.
 *
 * Gate: middleware('auth') saja (tanpa permission baru) — lihat routeSnippet.
 */
class TuDemoController extends Controller
{
    public function beranda(): Response
    {
        return Inertia::render('Tu/Beranda', [
            'userName' => Auth::user()?->name ?? 'Staf TU',
            'stats' => [
                ['key' => 'santri', 'label' => 'Santri Aktif', 'value' => '486', 'icon' => 'book', 'tone' => 'emerald', 'hint' => 'Tahun ajaran 2025/2026'],
                ['key' => 'masuk', 'label' => 'Surat Masuk', 'value' => '23', 'icon' => 'arrow-down-left', 'tone' => 'blue', 'hint' => 'Bulan Juni'],
                ['key' => 'keluar', 'label' => 'Surat Keluar', 'value' => '17', 'icon' => 'arrow-up-right', 'tone' => 'amber', 'hint' => 'Bulan Juni'],
                ['key' => 'arsip', 'label' => 'Arsip Dokumen', 'value' => '1.204', 'icon' => 'inbox', 'tone' => 'teal', 'hint' => 'Total terindeks'],
            ],
            'pintasan' => [
                ['key' => 'santri', 'label' => 'Data Santri', 'icon' => 'book', 'tone' => 'emerald', 'href' => '/tu/santri'],
                ['key' => 'surat', 'label' => 'Buku Agenda', 'icon' => 'document', 'tone' => 'blue', 'href' => '/tu/surat'],
                ['key' => 'catat', 'label' => 'Catat Surat', 'icon' => 'plus', 'tone' => 'amber', 'href' => '/tu/catat-surat'],
                ['key' => 'arsip', 'label' => 'Arsip', 'icon' => 'inbox', 'tone' => 'teal', 'href' => '/tu/arsip'],
            ],
            'agendaTerbaru' => [
                ['no' => 'AG-2026-0231', 'perihal' => 'Undangan Rapat Koordinasi KKM', 'dari' => 'Kemenag Kab. Sumedang', 'arah' => 'masuk', 'tanggal' => '12 Jun 2026'],
                ['no' => 'KLR-2026-0118', 'perihal' => 'Surat Keterangan Aktif Santri', 'dari' => 'Wali a.n. Fatih Abdullah', 'arah' => 'keluar', 'tanggal' => '11 Jun 2026'],
                ['no' => 'AG-2026-0230', 'perihal' => 'Permohonan Data Santri Penerima PIP', 'dari' => 'Dinas Sosial', 'arah' => 'masuk', 'tanggal' => '10 Jun 2026'],
            ],
            'navigation' => $this->nav(),
        ]);
    }

    public function santri(): Response
    {
        return Inertia::render('Tu/Santri', [
            'userName' => Auth::user()?->name ?? 'Staf TU',
            'kelasOptions' => [
                ['value' => 'semua', 'label' => 'Semua'],
                ['value' => 'VII', 'label' => 'VII'],
                ['value' => 'VIII', 'label' => 'VIII'],
                ['value' => 'IX', 'label' => 'IX'],
            ],
            'santri' => $this->daftarSantri(),
            'navigation' => $this->nav(),
        ]);
    }

    public function detailSantri(): Response
    {
        // Layar detail dipakai juga sebagai BottomSheet di list; method ini
        // menyediakan props utuh bila diakses sebagai halaman penuh.
        $daftar = $this->daftarSantri();

        return Inertia::render('Tu/DetailSantri', [
            'userName' => Auth::user()?->name ?? 'Staf TU',
            'santri' => $daftar[0],
            'navigation' => $this->nav(),
        ]);
    }

    public function surat(): Response
    {
        return Inertia::render('Tu/Surat', [
            'userName' => Auth::user()?->name ?? 'Staf TU',
            'arahOptions' => [
                ['value' => 'masuk', 'label' => 'Surat Masuk', 'icon' => 'arrow-down-left'],
                ['value' => 'keluar', 'label' => 'Surat Keluar', 'icon' => 'arrow-up-right'],
            ],
            'masuk' => [
                ['id' => 1, 'no' => 'AG-2026-0231', 'perihal' => 'Undangan Rapat Koordinasi KKM Kabupaten', 'dari' => 'Kemenag Kab. Sumedang', 'jenis' => 'Undangan', 'tanggal' => '12 Jun 2026'],
                ['id' => 2, 'no' => 'AG-2026-0230', 'perihal' => 'Permohonan Data Santri Penerima PIP', 'dari' => 'Dinas Sosial', 'jenis' => 'Permohonan', 'tanggal' => '10 Jun 2026'],
                ['id' => 3, 'no' => 'AG-2026-0229', 'perihal' => 'Pemberitahuan Jadwal Akreditasi Madrasah', 'dari' => 'Kemenag Kab. Sumedang', 'jenis' => 'Pemberitahuan', 'tanggal' => '08 Jun 2026'],
                ['id' => 4, 'no' => 'AG-2026-0228', 'perihal' => 'Surat Nikah Wali Santri (verifikasi data)', 'dari' => 'KUA Kec. Tanjungsari', 'jenis' => 'Keterangan', 'tanggal' => '06 Jun 2026'],
                ['id' => 5, 'no' => 'AG-2026-0227', 'perihal' => 'Undangan Pelatihan Operator EMIS', 'dari' => 'Kanwil Kemenag Jabar', 'jenis' => 'Undangan', 'tanggal' => '04 Jun 2026'],
                ['id' => 6, 'no' => 'AG-2026-0226', 'perihal' => 'Permohonan Izin Kunjungan Studi Banding', 'dari' => 'MTs Al-Hidayah', 'jenis' => 'Permohonan', 'tanggal' => '02 Jun 2026'],
            ],
            'keluar' => [
                ['id' => 11, 'no' => 'KLR-2026-0118', 'perihal' => 'Surat Keterangan Aktif Santri a.n. Fatih Abdullah', 'dari' => 'Wali Santri', 'jenis' => 'Keterangan', 'tanggal' => '11 Jun 2026'],
                ['id' => 12, 'no' => 'KLR-2026-0117', 'perihal' => 'Balasan Undangan Rapat Koordinasi KKM', 'dari' => 'Kemenag Kab. Sumedang', 'jenis' => 'Balasan', 'tanggal' => '10 Jun 2026'],
                ['id' => 13, 'no' => 'KLR-2026-0116', 'perihal' => 'Surat Tugas Pendamping Lomba MTQ Pelajar', 'dari' => 'Internal', 'jenis' => 'Surat Tugas', 'tanggal' => '07 Jun 2026'],
                ['id' => 14, 'no' => 'KLR-2026-0115', 'perihal' => 'Surat Keterangan Pindah Santri a.n. Rania', 'dari' => 'Wali Santri', 'jenis' => 'Keterangan', 'tanggal' => '05 Jun 2026'],
                ['id' => 15, 'no' => 'KLR-2026-0114', 'perihal' => 'Pengantar Pengajuan NISN Kolektif', 'dari' => 'Kemenag Kab. Sumedang', 'jenis' => 'Pengantar', 'tanggal' => '03 Jun 2026'],
            ],
            'navigation' => $this->nav(),
        ]);
    }

    public function catatSurat(): Response
    {
        return Inertia::render('Tu/CatatSurat', [
            'userName' => Auth::user()?->name ?? 'Staf TU',
            'jenisOptions' => [
                ['value' => 'Keterangan', 'label' => 'Keterangan'],
                ['value' => 'Pengantar', 'label' => 'Pengantar'],
                ['value' => 'Balasan', 'label' => 'Balasan'],
                ['value' => 'Surat Tugas', 'label' => 'Surat Tugas'],
            ],
            'nomorBerikutnya' => 'KLR-2026-0119',
            'navigation' => $this->nav(),
        ]);
    }

    public function arsip(): Response
    {
        return Inertia::render('Tu/Arsip', [
            'userName' => Auth::user()?->name ?? 'Staf TU',
            'kategoriOptions' => [
                ['value' => 'semua', 'label' => 'Semua'],
                ['value' => 'surat', 'label' => 'Surat'],
                ['value' => 'sk', 'label' => 'SK'],
                ['value' => 'sertifikat', 'label' => 'Sertifikat'],
            ],
            'dokumen' => [
                ['id' => 1, 'nama' => 'SK Pengangkatan Wali Kelas 2026', 'kategori' => 'sk', 'nomor' => 'SK-04/PP/VI/2026', 'tanggal' => '01 Jun 2026'],
                ['id' => 2, 'nama' => 'Sertifikat Akreditasi Madrasah B', 'kategori' => 'sertifikat', 'nomor' => 'AKR-118/BAN/2024', 'tanggal' => '15 Nov 2024'],
                ['id' => 3, 'nama' => 'Surat Keterangan Aktif a.n. Fatih', 'kategori' => 'surat', 'nomor' => 'KLR-2026-0118', 'tanggal' => '11 Jun 2026'],
                ['id' => 4, 'nama' => 'SK Susunan Panitia PPDB 2026/2027', 'kategori' => 'sk', 'nomor' => 'SK-03/PP/V/2026', 'tanggal' => '20 Mei 2026'],
                ['id' => 5, 'nama' => 'Sertifikat Juara 1 MTQ Pelajar', 'kategori' => 'sertifikat', 'nomor' => 'MTQ-22/2026', 'tanggal' => '18 Mei 2026'],
                ['id' => 6, 'nama' => 'Surat Pengantar NISN Kolektif', 'kategori' => 'surat', 'nomor' => 'KLR-2026-0114', 'tanggal' => '03 Jun 2026'],
                ['id' => 7, 'nama' => 'SK Kalender Pendidikan 2025/2026', 'kategori' => 'sk', 'nomor' => 'SK-01/PP/VII/2025', 'tanggal' => '10 Jul 2025'],
                ['id' => 8, 'nama' => 'Sertifikat Pelatihan Operator EMIS', 'kategori' => 'sertifikat', 'nomor' => 'EMIS-77/2025', 'tanggal' => '22 Agu 2025'],
            ],
            'navigation' => $this->nav(),
        ]);
    }

    /** Data induk santri dummy — dipakai layar Santri & DetailSantri. */
    private function daftarSantri(): array
    {
        return [
            ['id' => 1, 'nama' => 'Fatih Abdullah Rahman', 'nis' => '2526.0481', 'nisn' => '0123456781', 'kelas' => 'VII', 'ttl' => 'Sumedang, 14 Maret 2013', 'ayah' => 'Abdullah Maulana', 'ibu' => 'Siti Rohmah', 'alamat' => 'Kp. Cijambu RT 02/01, Tanjungsari, Sumedang'],
            ['id' => 2, 'nama' => 'Rania Nur Aulia', 'nis' => '2526.0482', 'nisn' => '0123456782', 'kelas' => 'VII', 'ttl' => 'Bandung, 02 Agustus 2013', 'ayah' => 'Hendra Gunawan', 'ibu' => 'Dewi Lestari', 'alamat' => 'Jl. Cibeunying No. 18, Bandung'],
            ['id' => 3, 'nama' => 'Muhammad Zaki Mubarak', 'nis' => '2425.0377', 'nisn' => '0119988771', 'kelas' => 'VIII', 'ttl' => 'Sumedang, 21 Januari 2012', 'ayah' => 'Asep Saepudin', 'ibu' => 'Nining Yuningsih', 'alamat' => 'Kp. Margamukti RT 04/03, Cimanggung'],
            ['id' => 4, 'nama' => 'Aisyah Putri Ramadhani', 'nis' => '2425.0378', 'nisn' => '0119988772', 'kelas' => 'VIII', 'ttl' => 'Garut, 11 September 2012', 'ayah' => 'Dadang Hermawan', 'ibu' => 'Euis Kurnia', 'alamat' => 'Jl. Guntur No. 45, Garut Kota'],
            ['id' => 5, 'nama' => 'Hafidz Ar-Rasyid', 'nis' => '2324.0265', 'nisn' => '0115566331', 'kelas' => 'IX', 'ttl' => 'Sumedang, 30 Juni 2011', 'ayah' => 'Rahmat Hidayat', 'ibu' => 'Lilis Suryani', 'alamat' => 'Kp. Sukamaju RT 01/05, Sukasari'],
            ['id' => 6, 'nama' => 'Naila Khairunnisa', 'nis' => '2324.0266', 'nisn' => '0115566332', 'kelas' => 'IX', 'ttl' => 'Cimahi, 17 April 2011', 'ayah' => 'Yusuf Maulana', 'ibu' => 'Siti Aminah', 'alamat' => 'Jl. Kolonel Masturi No. 9, Cimahi'],
            ['id' => 7, 'nama' => 'Ahmad Faiz Nugraha', 'nis' => '2526.0483', 'nisn' => '0123456783', 'kelas' => 'VII', 'ttl' => 'Sumedang, 05 Desember 2013', 'ayah' => 'Iwan Setiawan', 'ibu' => 'Yanti Mulyani', 'alamat' => 'Kp. Citali RT 03/02, Pamulihan'],
            ['id' => 8, 'nama' => 'Salma Azzahra', 'nis' => '2425.0379', 'nisn' => '0119988773', 'kelas' => 'VIII', 'ttl' => 'Sumedang, 28 Februari 2012', 'ayah' => 'Deni Rahman', 'ibu' => 'Imas Masitoh', 'alamat' => 'Kp. Cipacing RT 05/04, Jatinangor'],
        ];
    }

    /**
     * Navigasi bottom-bar (4 tab). Ikon SEMUA dari whitelist UiIcon:
     *   beranda->home, santri->book, surat->document, arsip->inbox.
     * active dihitung via request()->routeIs('tenant.tu.<x>*').
     */
    private function nav(): array
    {
        return [
            ['key' => 'beranda', 'label' => 'Beranda', 'icon' => 'home', 'href' => '/tu', 'active' => request()->routeIs('tenant.tu.beranda')],
            ['key' => 'santri', 'label' => 'Santri', 'icon' => 'book', 'href' => '/tu/santri', 'active' => request()->routeIs('tenant.tu.santri*') || request()->routeIs('tenant.tu.detail-santri')],
            ['key' => 'surat', 'label' => 'Surat', 'icon' => 'document', 'href' => '/tu/surat', 'active' => request()->routeIs('tenant.tu.surat') || request()->routeIs('tenant.tu.catat-surat')],
            ['key' => 'arsip', 'label' => 'Arsip', 'icon' => 'inbox', 'href' => '/tu/arsip', 'active' => request()->routeIs('tenant.tu.arsip')],
        ];
    }
}
