<?php

declare(strict_types=1);

namespace App\Http\Controllers\DemoApps;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * PWA Demo "HRD Kepegawaian" (Vue/Inertia) — showcase peragaan.
 *
 * SELURUH data adalah DUMMY INLINE (array PHP) agar layar selalu penuh & nol 500:
 * tidak ada query DB/model apa pun kecuali Auth::user()?->name untuk sapaan.
 * Aksi tulis (setujui/tolak cuti) ditangani optimistic di klien — tidak POST.
 *
 * Persona: Staf HRD. Gate: middleware('auth') saja (tanpa permission baru).
 */
class KepegawaianDemoController extends Controller
{
    /**
     * Item bottom-nav (kontrak {key,label,href,active}); aktif via nama rute.
     * Audit ikon: beranda->home, pegawai->user, absensi->check-circle,
     * slip->receipt, cuti->calendar (semua ada di whitelist UiIcon).
     */
    private function nav(): array
    {
        $base = '/kepegawaian';

        return [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => $base, 'route' => 'beranda', 'icon' => 'home'],
            ['key' => 'pegawai', 'label' => 'Pegawai', 'href' => "{$base}/pegawai", 'route' => 'pegawai', 'icon' => 'user'],
            ['key' => 'absensi', 'label' => 'Absensi', 'href' => "{$base}/absensi", 'route' => 'absensi', 'icon' => 'check-circle'],
            ['key' => 'slip', 'label' => 'Slip', 'href' => "{$base}/slip", 'route' => 'slip', 'icon' => 'receipt'],
            ['key' => 'cuti', 'label' => 'Cuti', 'href' => "{$base}/cuti", 'route' => 'cuti', 'icon' => 'calendar'],
        ];
    }

    /** Map nav -> kontrak final {key,label,href,active,icon}. */
    private function navItems(): array
    {
        return collect($this->nav())
            ->map(fn (array $i): array => [
                'key' => $i['key'],
                'label' => $i['label'],
                'href' => $i['href'],
                'icon' => $i['icon'],
                'active' => request()->routeIs("tenant.kepegawaian.{$i['route']}"),
            ])
            ->values()
            ->all();
    }

    /** Daftar pegawai dummy (dipakai list & detail). */
    private function pegawaiDummy(): array
    {
        return [
            ['id' => 1, 'nama' => 'Ustadz Abdul Hakim, S.Pd.I.', 'nip' => '198705122011011003', 'jabatan' => 'Kepala Madrasah', 'unit' => 'Madrasah Aliyah', 'status' => 'aktif', 'jenis' => 'Tetap', 'gaji_pokok' => 5200000, 'tunjangan' => 1800000, 'potongan' => 320000, 'kontrak_berakhir' => '2027-07-31', 'sisa_hari' => null],
            ['id' => 2, 'nama' => 'Ustadzah Siti Maryam, S.Ag.', 'nip' => '199003142015032001', 'jabatan' => 'Guru Tahfizh', 'unit' => 'Tahfizh Putri', 'status' => 'aktif', 'jenis' => 'Tetap', 'gaji_pokok' => 4100000, 'tunjangan' => 950000, 'potongan' => 210000, 'kontrak_berakhir' => '2026-08-05', 'sisa_hari' => 53],
            ['id' => 3, 'nama' => 'Bapak Hendra Gunawan', 'nip' => '198811202012061004', 'jabatan' => 'Kepala Tata Usaha', 'unit' => 'Administrasi', 'status' => 'aktif', 'jenis' => 'Tetap', 'gaji_pokok' => 3800000, 'tunjangan' => 700000, 'potongan' => 180000, 'kontrak_berakhir' => '2027-01-15', 'sisa_hari' => null],
            ['id' => 4, 'nama' => 'Ustadz Faisal Rahman, Lc.', 'nip' => '199205092018011002', 'jabatan' => 'Guru Bahasa Arab', 'unit' => 'Madrasah Aliyah', 'status' => 'aktif', 'jenis' => 'Kontrak', 'gaji_pokok' => 3500000, 'tunjangan' => 600000, 'potongan' => 150000, 'kontrak_berakhir' => '2026-07-20', 'sisa_hari' => 37],
            ['id' => 5, 'nama' => 'Ibu Nurul Hidayah, S.Pd.', 'nip' => '199407182019032003', 'jabatan' => 'Guru IPA', 'unit' => 'Madrasah Tsanawiyah', 'status' => 'aktif', 'jenis' => 'Kontrak', 'gaji_pokok' => 3300000, 'tunjangan' => 550000, 'potongan' => 140000, 'kontrak_berakhir' => '2026-09-30', 'sisa_hari' => 109],
            ['id' => 6, 'nama' => 'Bapak Slamet Riyadi', 'nip' => '198512012010071005', 'jabatan' => 'Petugas Keamanan', 'unit' => 'Umum', 'status' => 'aktif', 'jenis' => 'Tetap', 'gaji_pokok' => 2900000, 'tunjangan' => 400000, 'potongan' => 90000, 'kontrak_berakhir' => '2027-03-01', 'sisa_hari' => null],
            ['id' => 7, 'nama' => 'Ustadzah Aisyah Fitri, S.Psi.', 'nip' => '199601252020032004', 'jabatan' => 'Konselor BK', 'unit' => 'Bimbingan Konseling', 'status' => 'aktif', 'jenis' => 'Kontrak', 'gaji_pokok' => 3400000, 'tunjangan' => 600000, 'potongan' => 145000, 'kontrak_berakhir' => '2026-06-28', 'sisa_hari' => 15],
            ['id' => 8, 'nama' => 'Bapak Yusuf Maulana', 'nip' => '199009092016061006', 'jabatan' => 'Staf Dapur', 'unit' => 'Konsumsi', 'status' => 'aktif', 'jenis' => 'Tetap', 'gaji_pokok' => 2700000, 'tunjangan' => 350000, 'potongan' => 80000, 'kontrak_berakhir' => '2027-05-10', 'sisa_hari' => null],
            ['id' => 9, 'nama' => 'Ustadz Bahrul Ulum, S.Pd.', 'nip' => '199311112021011007', 'jabatan' => 'Guru Matematika', 'unit' => 'Madrasah Aliyah', 'status' => 'nonaktif', 'jenis' => 'Kontrak', 'gaji_pokok' => 3200000, 'tunjangan' => 500000, 'potongan' => 130000, 'kontrak_berakhir' => '2026-04-30', 'sisa_hari' => null],
            ['id' => 10, 'nama' => 'Ibu Dewi Lestari', 'nip' => '198706302009072008', 'jabatan' => 'Bendahara', 'unit' => 'Keuangan', 'status' => 'nonaktif', 'jenis' => 'Tetap', 'gaji_pokok' => 4000000, 'tunjangan' => 800000, 'potongan' => 200000, 'kontrak_berakhir' => '2025-12-31', 'sisa_hari' => null],
        ];
    }

    public function beranda()
    {
        return Inertia::render('Kepegawaian/Beranda', [
            'navigation' => $this->navItems(),
            'userName' => Auth::user()?->name,
            'stats' => [
                'pegawai_aktif' => 28,
                'persen_hadir' => 89,
                'slip_bulan' => 'Jumadil Akhir 1447',
                'kontrak_segera' => 3,
            ],
            'kontrakSegera' => [
                ['nama' => 'Ustadzah Aisyah Fitri, S.Psi.', 'jabatan' => 'Konselor BK', 'sisa_hari' => 15, 'berakhir' => '28 Jun 2026'],
                ['nama' => 'Ustadz Faisal Rahman, Lc.', 'jabatan' => 'Guru Bahasa Arab', 'sisa_hari' => 37, 'berakhir' => '20 Jul 2026'],
                ['nama' => 'Ustadzah Siti Maryam, S.Ag.', 'jabatan' => 'Guru Tahfizh', 'sisa_hari' => 53, 'berakhir' => '05 Agu 2026'],
            ],
        ]);
    }

    public function pegawai()
    {
        return Inertia::render('Kepegawaian/Pegawai', [
            'navigation' => $this->navItems(),
            'pegawai' => collect($this->pegawaiDummy())
                ->map(fn (array $p): array => [
                    'id' => $p['id'],
                    'nama' => $p['nama'],
                    'nip' => $p['nip'],
                    'jabatan' => $p['jabatan'],
                    'unit' => $p['unit'],
                    'status' => $p['status'],
                    'jenis' => $p['jenis'],
                ])
                ->all(),
        ]);
    }

    public function detailPegawai(int $id)
    {
        $p = collect($this->pegawaiDummy())->firstWhere('id', $id)
            ?? collect($this->pegawaiDummy())->first();

        return Inertia::render('Kepegawaian/DetailPegawai', [
            'navigation' => $this->navItems(),
            'pegawai' => $p,
            'riwayatSk' => [
                ['no_sk' => 'SK/HRD/2024/0142', 'judul' => 'Pengangkatan Jabatan', 'tanggal' => '01 Jul 2024'],
                ['no_sk' => 'SK/HRD/2023/0098', 'judul' => 'Kenaikan Golongan', 'tanggal' => '01 Jan 2023'],
                ['no_sk' => 'SK/HRD/2021/0051', 'judul' => 'Pengangkatan Pegawai Tetap', 'tanggal' => '15 Mar 2021'],
            ],
        ]);
    }

    public function absensi()
    {
        return Inertia::render('Kepegawaian/Absensi', [
            'navigation' => $this->navItems(),
            'ringkasan' => ['hadir' => 25, 'izin' => 2, 'alpa' => 1],
            'absensi' => [
                ['nama' => 'Ustadz Abdul Hakim, S.Pd.I.', 'jabatan' => 'Kepala Madrasah', 'status' => 'hadir', 'jam' => '06.52'],
                ['nama' => 'Ustadzah Siti Maryam, S.Ag.', 'jabatan' => 'Guru Tahfizh', 'status' => 'hadir', 'jam' => '06.48'],
                ['nama' => 'Bapak Hendra Gunawan', 'jabatan' => 'Kepala Tata Usaha', 'status' => 'hadir', 'jam' => '07.05'],
                ['nama' => 'Ustadz Faisal Rahman, Lc.', 'jabatan' => 'Guru Bahasa Arab', 'status' => 'izin', 'jam' => '—'],
                ['nama' => 'Ibu Nurul Hidayah, S.Pd.', 'jabatan' => 'Guru IPA', 'status' => 'hadir', 'jam' => '06.59'],
                ['nama' => 'Bapak Slamet Riyadi', 'jabatan' => 'Petugas Keamanan', 'status' => 'hadir', 'jam' => '05.40'],
                ['nama' => 'Ustadzah Aisyah Fitri, S.Psi.', 'jabatan' => 'Konselor BK', 'status' => 'izin', 'jam' => '—'],
                ['nama' => 'Bapak Yusuf Maulana', 'jabatan' => 'Staf Dapur', 'status' => 'hadir', 'jam' => '04.55'],
                ['nama' => 'Ustadz Bahrul Ulum, S.Pd.', 'jabatan' => 'Guru Matematika', 'status' => 'alpa', 'jam' => '—'],
            ],
        ]);
    }

    public function slip()
    {
        return Inertia::render('Kepegawaian/Slip', [
            'navigation' => $this->navItems(),
            'periode' => 'Jumadil Akhir 1447 H',
            'slip' => [
                ['id' => 1, 'nama' => 'Ustadz Abdul Hakim, S.Pd.I.', 'jabatan' => 'Kepala Madrasah', 'status' => 'final', 'bruto' => 7000000, 'tunjangan' => 1800000, 'potongan' => 320000, 'neto' => 6680000],
                ['id' => 2, 'nama' => 'Ustadzah Siti Maryam, S.Ag.', 'jabatan' => 'Guru Tahfizh', 'status' => 'final', 'bruto' => 5050000, 'tunjangan' => 950000, 'potongan' => 210000, 'neto' => 4840000],
                ['id' => 3, 'nama' => 'Bapak Hendra Gunawan', 'jabatan' => 'Kepala Tata Usaha', 'status' => 'final', 'bruto' => 4500000, 'tunjangan' => 700000, 'potongan' => 180000, 'neto' => 4320000],
                ['id' => 4, 'nama' => 'Ustadz Faisal Rahman, Lc.', 'jabatan' => 'Guru Bahasa Arab', 'status' => 'draft', 'bruto' => 4100000, 'tunjangan' => 600000, 'potongan' => 150000, 'neto' => 3950000],
                ['id' => 5, 'nama' => 'Ibu Nurul Hidayah, S.Pd.', 'jabatan' => 'Guru IPA', 'status' => 'draft', 'bruto' => 3850000, 'tunjangan' => 550000, 'potongan' => 140000, 'neto' => 3710000],
                ['id' => 6, 'nama' => 'Bapak Slamet Riyadi', 'jabatan' => 'Petugas Keamanan', 'status' => 'draft', 'bruto' => 3300000, 'tunjangan' => 400000, 'potongan' => 90000, 'neto' => 3210000],
            ],
        ]);
    }

    public function cuti()
    {
        return Inertia::render('Kepegawaian/Cuti', [
            'navigation' => $this->navItems(),
            'pengajuan' => [
                ['id' => 1, 'nama' => 'Ustadzah Siti Maryam, S.Ag.', 'jabatan' => 'Guru Tahfizh', 'jenis' => 'Cuti Tahunan', 'tanggal' => '18–20 Jun 2026', 'durasi' => 3, 'alasan' => 'Acara keluarga di kampung halaman.', 'status' => 'menunggu'],
                ['id' => 2, 'nama' => 'Ibu Nurul Hidayah, S.Pd.', 'jabatan' => 'Guru IPA', 'jenis' => 'Cuti Melahirkan', 'tanggal' => '01 Jul – 28 Sep 2026', 'durasi' => 90, 'alasan' => 'Cuti melahirkan anak pertama.', 'status' => 'menunggu'],
                ['id' => 3, 'nama' => 'Ustadz Faisal Rahman, Lc.', 'jabatan' => 'Guru Bahasa Arab', 'jenis' => 'Cuti Sakit', 'tanggal' => '12 Jun 2026', 'durasi' => 1, 'alasan' => 'Demam, surat keterangan dokter terlampir.', 'status' => 'menunggu'],
                ['id' => 4, 'nama' => 'Bapak Hendra Gunawan', 'jabatan' => 'Kepala Tata Usaha', 'jenis' => 'Cuti Tahunan', 'tanggal' => '05–06 Jun 2026', 'durasi' => 2, 'alasan' => 'Keperluan pribadi.', 'status' => 'terverifikasi'],
                ['id' => 5, 'nama' => 'Bapak Yusuf Maulana', 'jabatan' => 'Staf Dapur', 'jenis' => 'Cuti Sakit', 'tanggal' => '02 Jun 2026', 'durasi' => 1, 'alasan' => 'Sakit, tanpa surat dokter.', 'status' => 'ditolak'],
            ],
        ]);
    }
}
