<?php

declare(strict_types=1);

namespace App\Http\Controllers\DemoApps;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * POS Kantin — aplikasi PWA demo kasir kantin/koperasi pesantren.
 *
 * DEMO-GRADE: SEMUA data adalah dummy inline (array PHP) — tidak ada query DB
 * atau model sama sekali, KECUALI Auth::user()?->name untuk sapaan. Ini menjamin
 * nol error 500 & layar selalu penuh saat peragaan, terlepas dari isi tenant.
 *
 * Layar: Beranda, Kasir, Tabungan, Stok, Riwayat.
 * Gate: middleware('auth') saja — tidak ada permission baru.
 */
class PosKantinDemoController extends Controller
{
    public function beranda(): Response
    {
        return Inertia::render('PosKantin/Beranda', [
            'userName' => Auth::user()?->name,
            'omzetHariIni' => 1_250_000,
            'stats' => [
                ['key' => 'sesi', 'icon' => 'clock', 'tone' => 'teal', 'label' => 'Sesi Aktif', 'value' => 'Pagi', 'hint' => 'Dibuka 07.15 — Kasir 1'],
                ['key' => 'stok', 'icon' => 'inbox', 'tone' => 'amber', 'label' => 'Stok Rendah', 'value' => '3', 'hint' => 'Perlu restok hari ini'],
                ['key' => 'transaksi', 'icon' => 'receipt', 'tone' => 'blue', 'label' => 'Transaksi', 'value' => '84', 'hint' => 'Sejak sesi dibuka'],
            ],
            'terlaris' => [
                ['nama' => 'Roti Coklat', 'terjual' => 42, 'omzet' => 126_000],
                ['nama' => 'Susu Kotak', 'terjual' => 38, 'omzet' => 152_000],
                ['nama' => 'Mie Instan', 'terjual' => 31, 'omzet' => 108_500],
                ['nama' => 'Air Mineral', 'terjual' => 27, 'omzet' => 81_000],
            ],
            'navigation' => $this->nav(),
        ]);
    }

    public function kasir(): Response
    {
        return Inertia::render('PosKantin/Kasir', [
            'produk' => [
                ['id' => 1, 'nama' => 'Roti Coklat', 'harga' => 3_000, 'kategori' => 'Makanan', 'stok' => 24],
                ['id' => 2, 'nama' => 'Susu Kotak', 'harga' => 4_000, 'kategori' => 'Minuman', 'stok' => 18],
                ['id' => 3, 'nama' => 'Mie Instan', 'harga' => 3_500, 'kategori' => 'Makanan', 'stok' => 40],
                ['id' => 4, 'nama' => 'Air Mineral', 'harga' => 3_000, 'kategori' => 'Minuman', 'stok' => 60],
                ['id' => 5, 'nama' => 'Pulpen', 'harga' => 2_500, 'kategori' => 'ATK', 'stok' => 35],
                ['id' => 6, 'nama' => 'Buku Tulis', 'harga' => 5_000, 'kategori' => 'ATK', 'stok' => 22],
                ['id' => 7, 'nama' => 'Teh Gelas', 'harga' => 2_500, 'kategori' => 'Minuman', 'stok' => 4],
                ['id' => 8, 'nama' => 'Biskuit', 'harga' => 4_500, 'kategori' => 'Makanan', 'stok' => 16],
                ['id' => 9, 'nama' => 'Permen', 'harga' => 1_000, 'kategori' => 'Makanan', 'stok' => 80],
                ['id' => 10, 'nama' => 'Pensil', 'harga' => 2_000, 'kategori' => 'ATK', 'stok' => 5],
                ['id' => 11, 'nama' => 'Kopi Sachet', 'harga' => 2_000, 'kategori' => 'Minuman', 'stok' => 28],
                ['id' => 12, 'nama' => 'Wafer', 'harga' => 3_500, 'kategori' => 'Makanan', 'stok' => 13],
            ],
            'navigation' => $this->nav(),
        ]);
    }

    public function tabungan(): Response
    {
        return Inertia::render('PosKantin/Tabungan', [
            'saldoTotal' => 38_500_000,
            'jumlahSantri' => 312,
            'mutasi' => [
                ['id' => 1, 'santri' => 'Ahmad Fauzi', 'jenis' => 'topup', 'nominal' => 50_000, 'waktu' => 'Hari ini, 09.42'],
                ['id' => 2, 'santri' => 'Siti Aminah', 'jenis' => 'belanja', 'nominal' => 7_500, 'waktu' => 'Hari ini, 09.31'],
                ['id' => 3, 'santri' => 'Muhammad Rizki', 'jenis' => 'belanja', 'nominal' => 12_000, 'waktu' => 'Hari ini, 09.18'],
                ['id' => 4, 'santri' => 'Fatimah Zahra', 'jenis' => 'topup', 'nominal' => 100_000, 'waktu' => 'Hari ini, 08.55'],
                ['id' => 5, 'santri' => 'Abdullah Hakim', 'jenis' => 'belanja', 'nominal' => 6_000, 'waktu' => 'Hari ini, 08.40'],
                ['id' => 6, 'santri' => 'Khadijah Nur', 'jenis' => 'topup', 'nominal' => 75_000, 'waktu' => 'Kemarin, 16.20'],
                ['id' => 7, 'santri' => 'Umar Faruq', 'jenis' => 'belanja', 'nominal' => 9_500, 'waktu' => 'Kemarin, 15.05'],
            ],
            'navigation' => $this->nav(),
        ]);
    }

    public function stok(): Response
    {
        return Inertia::render('PosKantin/Stok', [
            'stats' => [
                ['key' => 'total', 'icon' => 'inbox', 'tone' => 'emerald', 'label' => 'Total Item', 'value' => '12'],
                ['key' => 'nilai', 'icon' => 'cash', 'tone' => 'teal', 'label' => 'Nilai Stok', 'value' => 'Rp 1,42 jt'],
                ['key' => 'rendah', 'icon' => 'inbox', 'tone' => 'amber', 'label' => 'Stok Rendah', 'value' => '3'],
            ],
            'items' => [
                ['id' => 1, 'nama' => 'Roti Coklat', 'kategori' => 'Makanan', 'stok' => 24, 'harga' => 3_000],
                ['id' => 2, 'nama' => 'Susu Kotak', 'kategori' => 'Minuman', 'stok' => 18, 'harga' => 4_000],
                ['id' => 3, 'nama' => 'Mie Instan', 'kategori' => 'Makanan', 'stok' => 40, 'harga' => 3_500],
                ['id' => 4, 'nama' => 'Air Mineral', 'kategori' => 'Minuman', 'stok' => 60, 'harga' => 3_000],
                ['id' => 5, 'nama' => 'Pulpen', 'kategori' => 'ATK', 'stok' => 35, 'harga' => 2_500],
                ['id' => 6, 'nama' => 'Buku Tulis', 'kategori' => 'ATK', 'stok' => 22, 'harga' => 5_000],
                ['id' => 7, 'nama' => 'Teh Gelas', 'kategori' => 'Minuman', 'stok' => 4, 'harga' => 2_500],
                ['id' => 8, 'nama' => 'Biskuit', 'kategori' => 'Makanan', 'stok' => 16, 'harga' => 4_500],
                ['id' => 9, 'nama' => 'Permen', 'kategori' => 'Makanan', 'stok' => 80, 'harga' => 1_000],
                ['id' => 10, 'nama' => 'Pensil', 'kategori' => 'ATK', 'stok' => 5, 'harga' => 2_000],
                ['id' => 11, 'nama' => 'Kopi Sachet', 'kategori' => 'Minuman', 'stok' => 28, 'harga' => 2_000],
                ['id' => 12, 'nama' => 'Wafer', 'kategori' => 'Makanan', 'stok' => 13, 'harga' => 3_500],
            ],
            'navigation' => $this->nav(),
        ]);
    }

    public function riwayat(): Response
    {
        return Inertia::render('PosKantin/Riwayat', [
            'sesi' => [
                'label' => 'Sesi Pagi',
                'dibuka' => '07.15',
                'kasir' => Auth::user()?->name ?? 'Kasir Kantin',
                'omzet' => 1_250_000,
                'transaksi' => 84,
                'tunai' => 720_000,
                'tabungan' => 530_000,
            ],
            'transaksi' => [
                ['id' => 'TRX-0084', 'item' => 'Roti Coklat ×2, Susu Kotak ×1', 'metode' => 'tabungan', 'nominal' => 10_000, 'waktu' => '09.42'],
                ['id' => 'TRX-0083', 'item' => 'Air Mineral ×1', 'metode' => 'tunai', 'nominal' => 3_000, 'waktu' => '09.38'],
                ['id' => 'TRX-0082', 'item' => 'Mie Instan ×2, Teh Gelas ×2', 'metode' => 'tunai', 'nominal' => 12_000, 'waktu' => '09.31'],
                ['id' => 'TRX-0081', 'item' => 'Buku Tulis ×1, Pulpen ×2', 'metode' => 'tabungan', 'nominal' => 10_000, 'waktu' => '09.18'],
                ['id' => 'TRX-0080', 'item' => 'Biskuit ×1, Permen ×3', 'metode' => 'tunai', 'nominal' => 7_500, 'waktu' => '09.05'],
                ['id' => 'TRX-0079', 'item' => 'Kopi Sachet ×2', 'metode' => 'tunai', 'nominal' => 4_000, 'waktu' => '08.52'],
            ],
            'navigation' => $this->nav(),
        ]);
    }

    /**
     * Item navigasi bottom-nav. Tiap key ikonnya diaudit ada di whitelist UiIcon:
     * beranda->home, kasir->cash, tabungan->wallet, stok->inbox, riwayat->receipt.
     */
    private function nav(): array
    {
        return [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => '/pos-kantin', 'icon' => 'home', 'active' => request()->routeIs('tenant.pos-kantin.beranda')],
            ['key' => 'kasir', 'label' => 'Kasir', 'href' => '/pos-kantin/kasir', 'icon' => 'cash', 'active' => request()->routeIs('tenant.pos-kantin.kasir')],
            ['key' => 'tabungan', 'label' => 'Tabungan', 'href' => '/pos-kantin/tabungan', 'icon' => 'wallet', 'active' => request()->routeIs('tenant.pos-kantin.tabungan')],
            ['key' => 'stok', 'label' => 'Stok', 'href' => '/pos-kantin/stok', 'icon' => 'inbox', 'active' => request()->routeIs('tenant.pos-kantin.stok')],
            ['key' => 'riwayat', 'label' => 'Riwayat', 'href' => '/pos-kantin/riwayat', 'icon' => 'receipt', 'active' => request()->routeIs('tenant.pos-kantin.riwayat')],
        ];
    }
}
