<?php

declare(strict_types=1);

namespace App\Http\Controllers\DemoApps;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * App PWA Demo "Kepengasuhan" — persona Musyrif / pengasuh asrama.
 *
 * DEMO-GRADE: seluruh data adalah dummy inline (array PHP) — TIDAK ada query
 * DB/model apa pun kecuali Auth::user()?->name untuk sapaan. Ini menjamin nol
 * 500 & layar selalu terisi penuh saat peragaan, lepas dari kondisi tenant.
 *
 * Gate: middleware('auth') saja (tanpa can:) — tidak menambah permission baru.
 *
 * Layar:
 *   - beranda           : ringkasan StatCard + daftar kamar + menu
 *   - presensiAsrama    : presensi malam per slot (Subuh/Maghrib/Apel Malam)
 *   - disiplin          : rekap pelanggaran bulan ini + total poin
 *   - catatPelanggaran  : form catat pelanggaran (optimistic)
 *   - izin              : daftar izin keluar/pulang + setujui/tolak
 */
class PengasuhanDemoController extends Controller
{
    /**
     * Item bottom-nav (kontrak {key,label,href,active}). Ikon di-set eksplisit
     * dari whitelist UiIcon agar lolos audit:
     *   hariini->calendar, presensi->check-circle, disiplin->chart, izin->document.
     */
    private function nav(): array
    {
        $base = '/pengasuhan';

        $items = [
            ['key' => 'hariini', 'label' => 'Hari Ini', 'icon' => 'calendar', 'href' => $base, 'route' => 'beranda'],
            ['key' => 'presensi', 'label' => 'Presensi', 'icon' => 'check-circle', 'href' => "{$base}/presensi", 'route' => 'presensi'],
            ['key' => 'disiplin', 'label' => 'Disiplin', 'icon' => 'chart', 'href' => "{$base}/disiplin", 'route' => 'disiplin'],
            ['key' => 'izin', 'label' => 'Izin', 'icon' => 'document', 'href' => "{$base}/izin", 'route' => 'izin'],
        ];

        return array_map(fn (array $i): array => [
            'key' => $i['key'],
            'label' => $i['label'],
            'icon' => $i['icon'],
            'href' => $i['href'],
            'active' => request()->routeIs("tenant.pengasuhan.{$i['route']}"),
        ], $items);
    }

    /** Sapaan musyrif (satu-satunya sentuhan data nyata). */
    private function userName(): string
    {
        return Auth::user()?->name ?: 'Ustadz Musyrif';
    }

    public function beranda(): Response
    {
        return Inertia::render('Pengasuhan/Beranda', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'ringkasan' => [
                'santri_binaan' => 42,
                'izin_aktif' => 3,
                'pelanggaran_pending' => 5,
                'kehadiran_malam' => '39/42',
            ],
            'kamar' => [
                ['id' => 1, 'nama' => 'Kamar Al-Faruq', 'lantai' => 'Lantai 1', 'jumlah' => 11, 'hadir' => 11, 'status' => 'lunas'],
                ['id' => 2, 'nama' => 'Kamar Az-Zahra', 'lantai' => 'Lantai 1', 'jumlah' => 10, 'hadir' => 9, 'status' => 'menunggu'],
                ['id' => 3, 'nama' => 'Kamar Al-Khansa', 'lantai' => 'Lantai 2', 'jumlah' => 11, 'hadir' => 11, 'status' => 'lunas'],
                ['id' => 4, 'nama' => 'Kamar Bilal', 'lantai' => 'Lantai 2', 'jumlah' => 10, 'hadir' => 8, 'status' => 'ditolak'],
            ],
        ]);
    }

    public function presensiAsrama(): Response
    {
        return Inertia::render('Pengasuhan/PresensiAsrama', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'kamar' => 'Kamar Al-Faruq · Lantai 1',
            'slotOptions' => [
                ['value' => 'subuh', 'label' => 'Subuh'],
                ['value' => 'maghrib', 'label' => 'Maghrib'],
                ['value' => 'apel', 'label' => 'Apel Malam'],
            ],
            'santri' => [
                ['id' => 1, 'nama' => 'Ahmad Fauzan', 'kelas' => "Kelas 7 · 'Aliyah", 'status' => 'hadir'],
                ['id' => 2, 'nama' => 'Bilal Ramadhan', 'kelas' => 'Kelas 7 · Tsanawiyah', 'status' => 'hadir'],
                ['id' => 3, 'nama' => 'Daffa Maulana', 'kelas' => 'Kelas 8 · Tsanawiyah', 'status' => 'izin'],
                ['id' => 4, 'nama' => 'Fikri Hidayat', 'kelas' => 'Kelas 8 · Tsanawiyah', 'status' => 'hadir'],
                ['id' => 5, 'nama' => 'Hanif Abdullah', 'kelas' => 'Kelas 9 · Tsanawiyah', 'status' => 'sakit'],
                ['id' => 6, 'nama' => 'Ilham Pratama', 'kelas' => 'Kelas 9 · Tsanawiyah', 'status' => 'hadir'],
                ['id' => 7, 'nama' => 'Khalil Gibran', 'kelas' => "Kelas 10 · 'Aliyah", 'status' => 'alpa'],
                ['id' => 8, 'nama' => 'Luthfi Hakim', 'kelas' => "Kelas 10 · 'Aliyah", 'status' => 'hadir'],
            ],
        ]);
    }

    public function disiplin(): Response
    {
        return Inertia::render('Pengasuhan/Disiplin', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'bulan' => 'Jumadil Akhir 1447 H',
            'totalPoin' => 87,
            'pelanggaran' => [
                ['id' => 1, 'santri' => 'Khalil Gibran', 'kelas' => "Kelas 10 · 'Aliyah", 'kategori' => 'Kedisiplinan', 'jenis' => 'Terlambat shalat berjamaah', 'poin' => 25, 'tanggal' => '8 Jumadil Akhir'],
                ['id' => 2, 'santri' => 'Daffa Maulana', 'kelas' => 'Kelas 8 · Tsanawiyah', 'kategori' => 'Ketertiban', 'jenis' => 'Tidak rapi seragam', 'poin' => 15, 'tanggal' => '7 Jumadil Akhir'],
                ['id' => 3, 'santri' => 'Hanif Abdullah', 'kelas' => 'Kelas 9 · Tsanawiyah', 'kategori' => 'Kebersihan', 'jenis' => 'Piket kamar tidak dikerjakan', 'poin' => 20, 'tanggal' => '5 Jumadil Akhir'],
                ['id' => 4, 'santri' => 'Bilal Ramadhan', 'kelas' => 'Kelas 7 · Tsanawiyah', 'kategori' => 'Akhlak', 'jenis' => 'Berkata kasar pada teman', 'poin' => 27, 'tanggal' => '3 Jumadil Akhir'],
            ],
        ]);
    }

    public function catatPelanggaran(): Response
    {
        return Inertia::render('Pengasuhan/CatatPelanggaran', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'santriOptions' => [
                ['id' => 1, 'nama' => 'Ahmad Fauzan'],
                ['id' => 2, 'nama' => 'Bilal Ramadhan'],
                ['id' => 3, 'nama' => 'Daffa Maulana'],
                ['id' => 4, 'nama' => 'Fikri Hidayat'],
                ['id' => 5, 'nama' => 'Hanif Abdullah'],
                ['id' => 6, 'nama' => 'Ilham Pratama'],
                ['id' => 7, 'nama' => 'Khalil Gibran'],
                ['id' => 8, 'nama' => 'Luthfi Hakim'],
            ],
            'jenisOptions' => [
                ['value' => 'kedisiplinan', 'label' => 'Disiplin'],
                ['value' => 'ketertiban', 'label' => 'Tertib'],
                ['value' => 'akhlak', 'label' => 'Akhlak'],
            ],
        ]);
    }

    public function izin(): Response
    {
        return Inertia::render('Pengasuhan/Izin', [
            'navigation' => $this->nav(),
            'userName' => $this->userName(),
            'daftar' => [
                ['id' => 1, 'santri' => 'Ilham Pratama', 'kelas' => 'Kelas 9 · Tsanawiyah', 'jenis' => 'Pulang', 'keperluan' => 'Acara keluarga di rumah', 'mulai' => 'Jum, 9 Jumadil Akhir', 'selesai' => 'Ahad, 11 Jumadil Akhir', 'status' => 'pending'],
                ['id' => 2, 'santri' => 'Fikri Hidayat', 'kelas' => 'Kelas 8 · Tsanawiyah', 'jenis' => 'Keluar', 'keperluan' => 'Kontrol ke dokter gigi', 'mulai' => 'Kam, 8 Jumadil Akhir', 'selesai' => 'Kam, 8 Jumadil Akhir', 'status' => 'pending'],
                ['id' => 3, 'santri' => 'Luthfi Hakim', 'kelas' => "Kelas 10 · 'Aliyah", 'jenis' => 'Pulang', 'keperluan' => 'Menjenguk orang tua sakit', 'mulai' => 'Sab, 10 Jumadil Akhir', 'selesai' => 'Sen, 12 Jumadil Akhir', 'status' => 'aktif'],
                ['id' => 4, 'santri' => 'Ahmad Fauzan', 'kelas' => "Kelas 7 · 'Aliyah", 'jenis' => 'Keluar', 'keperluan' => 'Beli perlengkapan sekolah', 'mulai' => 'Rab, 7 Jumadil Akhir', 'selesai' => 'Rab, 7 Jumadil Akhir', 'status' => 'terverifikasi'],
            ],
        ]);
    }
}
