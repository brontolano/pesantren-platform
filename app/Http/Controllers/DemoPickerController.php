<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\TenantBranding;
use Illuminate\Contracts\View\View;

/**
 * Halaman PEMILIH PERAN demo (etalase) — pengunjung memilih peran lalu satu klik
 * login sebagai akun demo peran itu (via /demo-masuk/{peran}) untuk merasakan
 * surface-nya. Digerbang sama dgn DemoAutoLoginController: hanya tenant demo +
 * DEMO_AUTOLOGIN. Selain itu → 404 (aman ikut ter-deploy ke produksi).
 */
class DemoPickerController extends Controller
{
    /**
     * Kelompok peran utk ditampilkan. `peran` HARUS ada di
     * DemoAutoLoginController::PERAN. Ikon = emoji (tanpa aset).
     *
     * @var array<int, array{judul:string, item:array<int,array{peran:string,label:string,desc:string,ikon:string}>}>
     */
    private const KELOMPOK = [
        [
            'judul' => 'Manajemen & Administrasi',
            'item' => [
                ['peran' => 'admin', 'label' => 'Admin Pesantren', 'desc' => 'Panel administrasi lengkap', 'ikon' => '🛠️'],
                ['peran' => 'super-admin', 'label' => 'Super Admin', 'desc' => 'Akses penuh seluruh modul', 'ikon' => '👑'],
                ['peran' => 'pimpinan', 'label' => 'Pimpinan / Kyai', 'desc' => 'Dasbor mutu & persetujuan', 'ikon' => '🕌'],
                ['peran' => 'tu', 'label' => 'Tata Usaha', 'desc' => 'Administrasi & surat', 'ikon' => '🗂️'],
                ['peran' => 'humas', 'label' => 'Humas', 'desc' => 'Website & informasi', 'ikon' => '📣'],
                ['peran' => 'ppdb-panitia', 'label' => 'Panitia PPDB', 'desc' => 'Penerimaan santri baru', 'ikon' => '📝'],
                ['peran' => 'kepegawaian', 'label' => 'Kepegawaian', 'desc' => 'Pegawai, absensi, slip, cuti', 'ikon' => '🧑‍💼'],
            ],
        ],
        [
            'judul' => 'Akademik & Pengasuhan',
            'item' => [
                ['peran' => 'guru', 'label' => 'Guru / Ustadz', 'desc' => 'Presensi, nilai, hafalan', 'ikon' => '👨‍🏫'],
                ['peran' => 'pengasuhan', 'label' => 'Musyrif / Pengasuhan', 'desc' => 'Pembinaan & perizinan', 'ikon' => '🤲'],
                ['peran' => 'uks', 'label' => 'Petugas UKS', 'desc' => 'Kesehatan santri', 'ikon' => '🏥'],
                ['peran' => 'keamanan', 'label' => 'Keamanan', 'desc' => 'Gerbang & ketertiban', 'ikon' => '🛡️'],
                ['peran' => 'konselor', 'label' => 'Konselor', 'desc' => 'Sesi konseling santri', 'ikon' => '💬'],
                ['peran' => 'sarpras', 'label' => 'Sarpras', 'desc' => 'Kerusakan & ceklis piket', 'ikon' => '🔧'],
            ],
        ],
        [
            'judul' => 'Keuangan',
            'item' => [
                ['peran' => 'keuangan', 'label' => 'Bendahara', 'desc' => 'Buku besar, tagihan, laporan', 'ikon' => '💰'],
                ['peran' => 'keuangan-lapangan', 'label' => 'Kasir Lapangan', 'desc' => 'Setor tabungan keliling', 'ikon' => '🧾'],
                ['peran' => 'pos-kantin', 'label' => 'Kasir Kantin', 'desc' => 'POS koperasi/kantin', 'ikon' => '🏪'],
            ],
        ],
        [
            'judul' => 'Keluarga, Santri & Publik',
            'item' => [
                ['peran' => 'wali', 'label' => 'Wali Santri', 'desc' => 'Tagihan, tabungan, nilai anak', 'ikon' => '👪'],
                ['peran' => 'santri', 'label' => 'Santri', 'desc' => 'Kartu, jadwal, tabungan', 'ikon' => '🎒'],
                ['peran' => 'jemaah', 'label' => 'Jemaah / Umum', 'desc' => 'Beranda publik & komentar', 'ikon' => '🌙'],
            ],
        ],
    ];

    public function show(): View
    {
        abort_unless((bool) config('demo.autologin_enabled'), 404);
        abort_unless(optional(tenant())->getTenantKey() === config('demo.autologin_tenant'), 404);

        return view('demo.picker', [
            'kelompok' => self::KELOMPOK,
            'namaPesantren' => TenantBranding::name(tenant()?->name ?? 'Pesantren Demo'),
            'warna' => TenantBranding::primaryColor('#0f5132'),
        ]);
    }
}
