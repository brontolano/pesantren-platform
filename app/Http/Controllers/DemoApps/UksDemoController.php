<?php

namespace App\Http\Controllers\DemoApps;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Demo PWA "Kesehatan UKS" (Vue/Inertia) — persona Petugas UKS/poliklinik.
 *
 * DEMO-GRADE: seluruh data adalah dummy inline (array PHP) — TIDAK ada query
 * DB/model apa pun kecuali Auth::user()?->name untuk sapaan. Ini menjamin nol
 * error 500 & layar selalu terisi penuh saat peragaan. Aksi tulis di sisi Vue
 * bersifat optimistic (BottomSheet konfirmasi, tanpa POST).
 *
 * Gate: middleware('auth') saja — tanpa permission baru.
 */
class UksDemoController extends Controller
{
    /**
     * Item bottom-nav (kontrak {key,label,href,active,icon}). Aktif via nama
     * rute tenant.uks.<x>. Ikon tiap key sudah diaudit ada di whitelist UiIcon:
     * home / check-circle / receipt / inbox / heart.
     */
    private function nav(): array
    {
        $base = '/uks';
        $items = [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => $base, 'route' => 'beranda', 'icon' => 'home'],
            ['key' => 'kunjungan', 'label' => 'Kunjungan', 'href' => "{$base}/kunjungan", 'route' => 'kunjungan', 'icon' => 'check-circle'],
            ['key' => 'obat', 'label' => 'Obat', 'href' => "{$base}/obat", 'route' => 'obat', 'icon' => 'receipt'],
            ['key' => 'rujukan', 'label' => 'Rujukan', 'href' => "{$base}/rujukan", 'route' => 'rujukan', 'icon' => 'inbox'],
            ['key' => 'riwayat', 'label' => 'Riwayat', 'href' => "{$base}/riwayat", 'route' => 'riwayat', 'icon' => 'heart'],
        ];

        return collect($items)
            ->map(fn (array $i): array => [
                'key' => $i['key'],
                'label' => $i['label'],
                'href' => $i['href'],
                'icon' => $i['icon'],
                'active' => request()->routeIs("tenant.uks.{$i['route']}"),
            ])
            ->all();
    }

    /** Opsional can() — disediakan utk konsistensi pola; demo tak butuh izin. */
    private function can(): array
    {
        return [
            'catat_kunjungan' => true,
            'kelola_obat' => true,
            'kelola_rujukan' => true,
        ];
    }

    public function beranda()
    {
        return Inertia::render('Uks/Beranda', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'userName' => Auth::user()?->name ?? 'Petugas UKS',
            'stats' => [
                ['key' => 'kunjungan', 'label' => 'Kunjungan Hari Ini', 'value' => '8', 'hint' => '3 sedang ditangani', 'icon' => 'check-circle', 'tone' => 'emerald'],
                ['key' => 'istirahat', 'label' => 'Santri Istirahat', 'value' => '2', 'hint' => 'di kamar pemulihan', 'icon' => 'heart', 'tone' => 'amber'],
                ['key' => 'rujukan', 'label' => 'Rujukan Terbuka', 'value' => '1', 'hint' => 'menunggu jemputan', 'icon' => 'inbox', 'tone' => 'rose'],
                ['key' => 'obat', 'label' => 'Obat Menipis', 'value' => '3', 'hint' => 'stok ≤ 5', 'icon' => 'receipt', 'tone' => 'blue'],
            ],
            'kunjunganTerbaru' => [
                ['id' => 1, 'nama' => 'Ahmad Fauzi', 'kelas' => '8A', 'keluhan' => 'Demam & pusing', 'status' => 'istirahat', 'jam' => '09:15'],
                ['id' => 2, 'nama' => 'Bilal Ramadhan', 'kelas' => '7B', 'keluhan' => 'Luka lecet kaki', 'status' => 'pulang', 'jam' => '09:40'],
                ['id' => 3, 'nama' => 'Hafidz Maulana', 'kelas' => '9C', 'keluhan' => 'Sakit perut', 'status' => 'pulang', 'jam' => '10:05'],
                ['id' => 4, 'nama' => 'Yusuf Abdullah', 'kelas' => '8B', 'keluhan' => 'Sesak napas (asma)', 'status' => 'rujuk', 'jam' => '10:30'],
            ],
        ]);
    }

    public function kunjungan()
    {
        return Inertia::render('Uks/Kunjungan', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'kunjungan' => [
                ['id' => 1, 'nama' => 'Ahmad Fauzi', 'kelas' => '8A', 'keluhan' => 'Demam & pusing', 'tindakan' => 'Paracetamol, kompres', 'status' => 'istirahat', 'jam' => '09:15'],
                ['id' => 2, 'nama' => 'Bilal Ramadhan', 'kelas' => '7B', 'keluhan' => 'Luka lecet kaki', 'tindakan' => 'Bersihkan luka, Betadine, Hansaplast', 'status' => 'pulang', 'jam' => '09:40'],
                ['id' => 3, 'nama' => 'Hafidz Maulana', 'kelas' => '9C', 'keluhan' => 'Sakit perut, mual', 'tindakan' => 'Antasida, istirahat sebentar', 'status' => 'pulang', 'jam' => '10:05'],
                ['id' => 4, 'nama' => 'Yusuf Abdullah', 'kelas' => '8B', 'keluhan' => 'Sesak napas (asma)', 'tindakan' => 'Inhaler, rujuk Puskesmas', 'status' => 'rujuk', 'jam' => '10:30'],
                ['id' => 5, 'nama' => 'Rizky Saputra', 'kelas' => '7A', 'keluhan' => 'Batuk & tenggorokan gatal', 'tindakan' => 'OBH, Vitamin C', 'status' => 'pulang', 'jam' => '10:50'],
                ['id' => 6, 'nama' => 'Faisal Nur', 'kelas' => '9A', 'keluhan' => 'Pegal otot olahraga', 'tindakan' => 'Salonpas, istirahat', 'status' => 'istirahat', 'jam' => '11:10'],
            ],
        ]);
    }

    public function catatKunjungan()
    {
        return Inertia::render('Uks/CatatKunjungan', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'santriOptions' => [
                ['value' => 'ahmad', 'label' => 'Ahmad Fauzi (8A)'],
                ['value' => 'bilal', 'label' => 'Bilal Ramadhan (7B)'],
                ['value' => 'hafidz', 'label' => 'Hafidz Maulana (9C)'],
                ['value' => 'yusuf', 'label' => 'Yusuf Abdullah (8B)'],
                ['value' => 'rizky', 'label' => 'Rizky Saputra (7A)'],
                ['value' => 'faisal', 'label' => 'Faisal Nur (9A)'],
            ],
        ]);
    }

    public function obat()
    {
        return Inertia::render('Uks/Obat', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'obat' => [
                ['id' => 1, 'nama' => 'Paracetamol 500mg', 'satuan' => 'tablet', 'stok' => 48],
                ['id' => 2, 'nama' => 'Amoxicillin 500mg', 'satuan' => 'kapsul', 'stok' => 4],
                ['id' => 3, 'nama' => 'Antasida', 'satuan' => 'tablet', 'stok' => 30],
                ['id' => 4, 'nama' => 'OBH Sirup', 'satuan' => 'botol', 'stok' => 5],
                ['id' => 5, 'nama' => 'Betadine', 'satuan' => 'botol', 'stok' => 9],
                ['id' => 6, 'nama' => 'Oralit', 'satuan' => 'sachet', 'stok' => 22],
                ['id' => 7, 'nama' => 'Vitamin C', 'satuan' => 'tablet', 'stok' => 60],
                ['id' => 8, 'nama' => 'Hansaplast', 'satuan' => 'lembar', 'stok' => 3],
                ['id' => 9, 'nama' => 'Minyak Kayu Putih', 'satuan' => 'botol', 'stok' => 7],
                ['id' => 10, 'nama' => 'Salonpas', 'satuan' => 'lembar', 'stok' => 14],
            ],
        ]);
    }

    public function rujukan()
    {
        return Inertia::render('Uks/Rujukan', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'rujukan' => [
                ['id' => 1, 'nama' => 'Yusuf Abdullah', 'kelas' => '8B', 'keluhan' => 'Serangan asma berulang', 'tujuan' => 'Puskesmas Cibeureum', 'biaya' => 75000, 'status' => 'menunggu', 'tanggal' => '13 Jun 2026'],
                ['id' => 2, 'nama' => 'Ikhwan Maulana', 'kelas' => '9B', 'keluhan' => 'Patah tulang lengan (jatuh)', 'tujuan' => 'RS Al-Islam', 'biaya' => 450000, 'status' => 'terverifikasi', 'tanggal' => '12 Jun 2026'],
                ['id' => 3, 'nama' => 'Dani Hermawan', 'kelas' => '7C', 'keluhan' => 'Tifus, perlu rawat inap', 'tujuan' => 'RS Al-Islam', 'biaya' => 1250000, 'status' => 'terverifikasi', 'tanggal' => '10 Jun 2026'],
                ['id' => 4, 'nama' => 'Salman Alfarisi', 'kelas' => '8C', 'keluhan' => 'Pemeriksaan mata', 'tujuan' => 'Puskesmas Cibeureum', 'biaya' => 50000, 'status' => 'menunggu', 'tanggal' => '13 Jun 2026'],
            ],
        ]);
    }

    public function riwayat()
    {
        return Inertia::render('Uks/Riwayat', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'riwayat' => [
                ['id' => 1, 'nama' => 'Ahmad Fauzi', 'kelas' => '8A', 'diagnosa' => 'Demam (suspek flu)', 'tindakan' => 'Paracetamol, istirahat', 'status' => 'istirahat', 'tanggal' => '13 Jun 2026', 'jam' => '09:15'],
                ['id' => 2, 'nama' => 'Bilal Ramadhan', 'kelas' => '7B', 'diagnosa' => 'Luka lecet kaki', 'tindakan' => 'Perawatan luka', 'status' => 'pulang', 'tanggal' => '13 Jun 2026', 'jam' => '09:40'],
                ['id' => 3, 'nama' => 'Yusuf Abdullah', 'kelas' => '8B', 'diagnosa' => 'Asma kambuh', 'tindakan' => 'Rujuk Puskesmas', 'status' => 'rujuk', 'tanggal' => '13 Jun 2026', 'jam' => '10:30'],
                ['id' => 4, 'nama' => 'Hafidz Maulana', 'kelas' => '9C', 'diagnosa' => 'Maag', 'tindakan' => 'Antasida', 'status' => 'pulang', 'tanggal' => '12 Jun 2026', 'jam' => '14:20'],
                ['id' => 5, 'nama' => 'Ikhwan Maulana', 'kelas' => '9B', 'diagnosa' => 'Fraktur lengan', 'tindakan' => 'Rujuk RS Al-Islam', 'status' => 'rujuk', 'tanggal' => '12 Jun 2026', 'jam' => '08:05'],
                ['id' => 6, 'nama' => 'Rizky Saputra', 'kelas' => '7A', 'diagnosa' => 'ISPA ringan', 'tindakan' => 'OBH, Vitamin C', 'status' => 'pulang', 'tanggal' => '11 Jun 2026', 'jam' => '15:45'],
                ['id' => 7, 'nama' => 'Faisal Nur', 'kelas' => '9A', 'diagnosa' => 'Cedera otot', 'tindakan' => 'Salonpas, istirahat', 'status' => 'istirahat', 'tanggal' => '11 Jun 2026', 'jam' => '11:10'],
            ],
        ]);
    }
}
