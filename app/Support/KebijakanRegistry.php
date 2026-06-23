<?php

namespace App\Support;

use Modules\Akademik\Services\KalenderService;
use Modules\Akademik\Services\KelulusanService;
use Modules\Akademik\Services\KenaikanKelasService;
use Modules\Core\Services\SuratService;
use Modules\Kesantrian\Services\DisiplinService;
use Modules\Kesantrian\Services\PresensiAsramaService;
use Modules\Payroll\Models\KontrakKerja;

/**
 * WP-34 — Registry SEMUA kebijakan (Setting policy) yang sudah dibuka modul
 * G1–G4. Bukti janji inti platform: "sistem ikut pesantren, bukan sebaliknya".
 *
 * Sumber kebenaran TUNGGAL untuk halaman "Kebijakan Pesantren":
 * key Setting, label awam, deskripsi 1 kalimat, default (nilai efektif saat
 * tidak ada override), kelompok, dan tipe nilai.
 *
 * CATATAN: nilai disimpan MENTAH di Setting (service membaca toleran —
 * skalar langsung maupun bungkus ['value'=>..] sama-sama dipahami pembaca).
 * Default = null berarti "ikut bawaan modul" (baris Setting dihapus saat reset).
 */
class KebijakanRegistry
{
    public const KELOMPOK = [
        'disiplin' => 'Disiplin & Ta\'zir',
        'izin' => 'Izin & Penjengukan',
        'kepengasuhan' => 'Kepengasuhan',
        'akademik' => 'Akademik',
        'keuangan' => 'Keuangan & Payroll',
        'migrasi' => 'Impor & Migrasi Data',
        'umum' => 'Umum',
    ];

    /**
     * Entry tipe 'number' boleh membawa 'min'/'max' (batas wajar, ditegakkan
     * KebijakanService::set() dan form Filament).
     *
     * @return array<string, array{label:string, deskripsi:string, default:mixed, kelompok:string, tipe:string, options?:array<string,string>, min?:int|float, max?:int|float}>
     */
    public static function entries(): array
    {
        return [
            // ---------------- Disiplin ----------------
            DisiplinService::SETTING_WAJIB_VERIFIKASI => [
                'label' => 'Catatan pelanggaran wajib diverifikasi ustadz',
                'deskripsi' => 'Bila aktif, catatan pelanggaran baru berlaku setelah diperiksa ustadz lain (pencatat tidak boleh memverifikasi sendiri).',
                'default' => true, 'kelompok' => 'disiplin', 'tipe' => 'bool',
            ],
            DisiplinService::SETTING_AMBANG => [
                'label' => 'Ambang poin Surat Peringatan (SP)',
                'deskripsi' => 'Daftar batas poin yang memicu SP otomatis, contoh: 50 poin = SP1, 100 poin = SP2.',
                'default' => DisiplinService::DEFAULT_AMBANG, 'kelompok' => 'disiplin', 'tipe' => 'json',
            ],
            DisiplinService::SETTING_NOTIF_WALI => [
                'label' => 'Kapan wali diberi tahu soal pelanggaran',
                'deskripsi' => 'Pilih kapan orang tua/wali menerima kabar pelanggaran anaknya.',
                'default' => 'eskalasi', 'kelompok' => 'disiplin', 'tipe' => 'select',
                'options' => ['semua' => 'Setiap pelanggaran', 'eskalasi' => 'Hanya saat kena SP', 'berat' => 'Hanya pelanggaran berat', 'mati' => 'Tidak pernah'],
            ],
            DisiplinService::SETTING_POIN_POSITIF => [
                'label' => 'Poin positif / prestasi diaktifkan',
                'deskripsi' => 'Bila aktif, ustadz bisa mencatat prestasi santri sebagai poin pengurang pelanggaran.',
                'default' => true, 'kelompok' => 'disiplin', 'tipe' => 'bool',
            ],
            DisiplinService::SETTING_MASA_BERLAKU => [
                'label' => 'Masa berlaku poin pelanggaran',
                'deskripsi' => 'Menentukan kapan hitungan poin dikosongkan kembali.',
                'default' => 'semester', 'kelompok' => 'disiplin', 'tipe' => 'select',
                'options' => ['semester' => 'Per semester', 'tahunan' => 'Per tahun ajaran', 'akumulasi' => 'Tidak pernah (akumulasi terus)'],
            ],

            // ---------------- Izin & Penjengukan ----------------
            'kesantrian.maks_izin_bulan' => [
                'label' => 'Batas izin pulang per bulan',
                'deskripsi' => 'Jumlah maksimal pengajuan izin pulang seorang santri dalam satu bulan.',
                'default' => 2, 'kelompok' => 'izin', 'tipe' => 'number', 'min' => 0, 'max' => 31,
            ],
            'kesantrian.approval_h_minimum' => [
                'label' => 'Izin harus diajukan minimal H-berapa',
                'deskripsi' => 'Izin pulang wajib diajukan sekian hari sebelum tanggal mulai (0 = boleh hari yang sama).',
                'default' => 3, 'kelompok' => 'izin', 'tipe' => 'number', 'min' => 0, 'max' => 30,
            ],
            'kesantrian.jadwal_penjengukan' => [
                'label' => 'Jadwal penjengukan santri',
                'deskripsi' => 'Hari, jam, kuota, dan aturan konfirmasi kunjungan wali ke pesantren.',
                'default' => [
                    'hari' => ['ahad'], 'jam_mulai' => '08:00', 'jam_selesai' => '15:00',
                    'kuota_per_slot' => 20, 'konfirmasi' => 'otomatis', 'walk_in_boleh' => true,
                ],
                'kelompok' => 'izin', 'tipe' => 'json',
            ],
            'kesantrian.template_pesan_wali' => [
                'label' => 'Template pesan ke wali',
                'deskripsi' => 'Daftar template pesan siap pakai untuk guru saat mengirim kabar ke wali (kosong = ikut bawaan modul).',
                'default' => null, 'kelompok' => 'izin', 'tipe' => 'json',
            ],
            'kesantrian.moderasi_pesan_perhatian' => [
                'label' => 'Pesan "perhatian" ke wali dimoderasi dulu',
                'deskripsi' => 'Bila aktif, pesan bernada perhatian/teguran diperiksa admin sebelum terkirim ke wali.',
                'default' => true, 'kelompok' => 'izin', 'tipe' => 'bool',
            ],

            // ---------------- Kepengasuhan ----------------
            PresensiAsramaService::SETTING_SLOT_PRESENSI => [
                'label' => 'Slot presensi asrama',
                'deskripsi' => 'Daftar waktu absen asrama (contoh: Subuh, Maghrib, Isya) beserta jam buka-tutupnya.',
                'default' => PresensiAsramaService::DEFAULT_SLOTS, 'kelompok' => 'kepengasuhan', 'tipe' => 'json',
            ],
            PresensiAsramaService::SETTING_DEFAULT_HADIR => [
                'label' => 'Presensi asrama default "hadir"',
                'deskripsi' => 'Bila aktif, daftar absen dibuka dengan semua santri tercentang hadir (musyrif tinggal menandai yang tidak hadir).',
                'default' => true, 'kelompok' => 'kepengasuhan', 'tipe' => 'bool',
            ],

            // ---------------- Akademik ----------------
            KalenderService::SETTING_HARI_LIBUR_MINGGUAN => [
                'label' => 'Hari libur mingguan',
                'deskripsi' => 'Hari libur rutin tiap pekan — banyak pesantren libur Jumat, bukan Ahad.',
                'default' => KalenderService::DEFAULT_HARI_LIBUR_MINGGUAN, 'kelompok' => 'akademik', 'tipe' => 'json',
            ],
            KenaikanKelasService::SETTING_PREFIX.'default' => [
                'label' => 'Syarat kenaikan kelas (bawaan)',
                'deskripsi' => 'Ambang nilai, kehadiran, poin disiplin, dan syarat tahfidz untuk rekomendasi naik kelas.',
                'default' => KenaikanKelasService::DEFAULTS, 'kelompok' => 'akademik', 'tipe' => 'json',
            ],
            KenaikanKelasService::SETTING_MODE_PENEMPATAN => [
                'label' => 'Mode penempatan kelas',
                'deskripsi' => 'Berjenjang = naik kelas urut tingkat; bebas = penempatan fleksibel (mu\'adalah).',
                'default' => 'berjenjang', 'kelompok' => 'akademik', 'tipe' => 'select',
                'options' => ['berjenjang' => 'Berjenjang (urut tingkat)', 'bebas' => 'Bebas (mu\'adalah)'],
            ],
            'akademik.rapor_diniyah_blok' => [
                'label' => 'Blok rapor diniyah yang dipakai',
                'deskripsi' => 'Pilih bagian-bagian rapor diniyah yang aktif untuk pesantren ini (kosong = semua blok).',
                'default' => null, 'kelompok' => 'akademik', 'tipe' => 'json',
            ],
            KelulusanService::SETTING_FORMAT_NOMOR => [
                'label' => 'Format nomor syahadah/ijazah',
                'deskripsi' => 'Pola penomoran syahadah; {counter}, {lembaga}, {tahun} diganti otomatis.',
                'default' => KelulusanService::DEFAULT_FORMAT_NOMOR, 'kelompok' => 'akademik', 'tipe' => 'text',
            ],
            'akademik_grade_weights' => [
                'label' => 'Bobot nilai harian / UTS / UAS',
                'deskripsi' => 'Persentase bobot nilai akhir bila tidak memakai komponen nilai kustom.',
                'default' => ['harian' => 30, 'uts' => 30, 'uas' => 40], 'kelompok' => 'akademik', 'tipe' => 'json',
            ],
            'akademik_kehadiran_minimal' => [
                'label' => 'Ambang kehadiran minimal (%)',
                'deskripsi' => 'Persentase kehadiran minimum santri sebelum ditandai bermasalah pada KPI akademik.',
                'default' => 80, 'kelompok' => 'akademik', 'tipe' => 'number', 'min' => 0, 'max' => 100,
            ],

            // ---------------- Keuangan & Payroll ----------------
            'payroll.hari_kerja_pembagi' => [
                'label' => 'Pembagi hari kerja potongan cuti',
                'deskripsi' => 'Dasar pembagi potongan gaji per hari: ikuti kalender efektif, atau angka tetap (mis. 26).',
                'default' => 'kalender_efektif', 'kelompok' => 'keuangan', 'tipe' => 'text',
            ],
            'payroll.cuti_default_berbayar' => [
                'label' => 'Cuti default berbayar',
                'deskripsi' => 'Bila aktif, pengajuan cuti baru otomatis dianggap cuti berbayar (tidak memotong gaji).',
                'default' => true, 'kelompok' => 'keuangan', 'tipe' => 'bool',
            ],
            KontrakKerja::SETTING_REMINDER_HARI => [
                'label' => 'Pengingat kontrak habis (hari)',
                'deskripsi' => 'Berapa hari sebelum kontrak/SK berakhir sistem mulai mengingatkan.',
                'default' => KontrakKerja::DEFAULT_REMINDER_HARI, 'kelompok' => 'keuangan', 'tipe' => 'number', 'min' => 1, 'max' => 365,
            ],
            'payroll.thr_formula' => [
                'label' => 'Rumus THR',
                'deskripsi' => 'Cara menghitung THR pegawai saat generate massal.',
                'default' => 'nominal_tetap', 'kelompok' => 'keuangan', 'tipe' => 'select',
                'options' => ['nominal_tetap' => 'Nominal tetap sama rata', 'kali_honor_pokok' => 'Kelipatan honor pokok', 'manual' => 'Diisi manual per pegawai'],
            ],
            'payroll.thr_nominal' => [
                'label' => 'Nominal THR tetap (Rp)',
                'deskripsi' => 'Dipakai bila rumus THR = nominal tetap.',
                'default' => 0, 'kelompok' => 'keuangan', 'tipe' => 'number', 'min' => 0,
            ],
            'payroll.thr_pengali' => [
                'label' => 'Pengali THR dari honor pokok',
                'deskripsi' => 'Dipakai bila rumus THR = kelipatan honor pokok (contoh: 1 = satu kali gaji pokok).',
                'default' => 1, 'kelompok' => 'keuangan', 'tipe' => 'number', 'min' => 0, 'max' => 12,
            ],
            'keuangan.dscr.ambang' => [
                'label' => 'Ambang kelayakan kredit (DSCR)',
                'deskripsi' => 'Batas rasio kemampuan bayar utang; di bawah ambang ini pengajuan pembiayaan ditandai berisiko.',
                'default' => 1.25, 'kelompok' => 'keuangan', 'tipe' => 'number', 'min' => 0,
            ],
            'keuangan.payroll.tarif_poin' => [
                'label' => 'Tarif honor per poin (Rp)',
                'deskripsi' => 'Honor kegiatan = jumlah poin × tarif ini.',
                'default' => 10000, 'kelompok' => 'keuangan', 'tipe' => 'number', 'min' => 0,
            ],
            'keuangan.payroll.pph21_persen' => [
                'label' => 'Tarif PPh 21 (%)',
                'deskripsi' => 'Persentase potongan pajak penghasilan pada slip gaji (0 = tidak dipotong).',
                'default' => 0, 'kelompok' => 'keuangan', 'tipe' => 'number', 'min' => 0, 'max' => 100,
            ],
            'keuangan.jenis_lembaga' => [
                'label' => 'Jenis lembaga (rezim BOS/BOP)',
                'deskripsi' => 'Menentukan template dana negara: BOS Madrasah atau BOP Pesantren.',
                'default' => 'pesantren', 'kelompok' => 'keuangan', 'tipe' => 'select',
                'options' => ['pesantren' => 'Pesantren (BOP)', 'madrasah' => 'Madrasah (BOS)'],
            ],
            'keuangan.pemisahan_kehalalan_aktif' => [
                'label' => 'Pemisahan dana halal & syubhat',
                'deskripsi' => 'Bila aktif, dana syubhat (mis. bunga bank) dipisah sebagai Dana Kebajikan dan dijaga penyalurannya.',
                'default' => false, 'kelompok' => 'keuangan', 'tipe' => 'bool',
            ],

            // ---------------- Umum (Administrasi Surat) ----------------
            SuratService::SETTING_KODE_LEMBAGA => [
                'label' => 'Kode lembaga pada nomor surat keluar',
                'deskripsi' => 'Singkatan lembaga yang muncul di nomor surat keluar, mis. PES/MA/MTS (kosong = bawaan "PES").',
                'default' => SuratService::DEFAULT_KODE_LEMBAGA, 'kelompok' => 'umum', 'tipe' => 'text',
            ],
            SuratService::SETTING_FORMAT_NOMOR => [
                'label' => 'Format nomor surat keluar',
                'deskripsi' => 'Pola penomoran surat keluar; {urut}, {kode}, {bulan} (romawi), {tahun} diganti otomatis.',
                'default' => SuratService::DEFAULT_FORMAT_NOMOR, 'kelompok' => 'umum', 'tipe' => 'text',
            ],

            // ---------------- Impor & Migrasi Data ----------------
            // Fitur impor opsional: default MATI (konservatif) — tiap pesantren
            // mengaktifkan sesuai kebijakan. Gerbang dibaca trait HasImportAction.
            'import.pelanggaran_historis' => [
                'label' => 'Izinkan impor data pelanggaran/prestasi lama',
                'deskripsi' => 'Bila aktif, muncul tombol impor riwayat pelanggaran. Data lama diimpor TANPA notifikasi wali & TANPA SP otomatis. Biarkan mati bila pesantren memulai catatan disiplin dari nol.',
                'default' => false, 'kelompok' => 'migrasi', 'tipe' => 'bool',
            ],
            'import.presensi_staf_historis' => [
                'label' => 'Izinkan impor presensi staf/pegawai lama',
                'deskripsi' => 'Bila aktif, muncul tombol impor presensi pegawai. PERHATIAN: presensi staf ikut menghitung poin Khidmah/Bisyaroh — aktifkan hanya bila paham dampaknya pada penggajian.',
                'default' => false, 'kelompok' => 'migrasi', 'tipe' => 'bool',
            ],
            'import.kalender_kustom' => [
                'label' => 'Izinkan impor kalender akademik massal',
                'deskripsi' => 'Bila aktif, muncul tombol impor entri kalender (libur pondok/ujian/pasaran) dari spreadsheet. Libur nasional sudah terisi otomatis; ini untuk acara khusus pesantren.',
                'default' => false, 'kelompok' => 'migrasi', 'tipe' => 'bool',
            ],
            // Catatan: presensi KEGIATAN sengaja TIDAK diberi importer/saklar —
            // tak punya halaman/resource Filament sebagai rumah tombol & bersifat
            // operasional (bukan data migrasi awal). Ditinjau & dilewati sadar.
        ];
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(static::entries());
    }

    /** @return array<string, array<string, mixed>> kelompok => entries */
    public static function byKelompok(): array
    {
        $grouped = array_fill_keys(array_keys(self::KELOMPOK), []);
        foreach (static::entries() as $key => $entry) {
            $grouped[$entry['kelompok']][$key] = $entry;
        }

        return array_filter($grouped);
    }

    /**
     * Preset kebijakan bawaan — nilai wajar untuk dua arketipe pesantren.
     *
     * @return array<string, array{label:string, deskripsi:string, nilai:array<string, mixed>}>
     */
    public static function presets(): array
    {
        return [
            'salafiyah-kecil' => [
                'label' => 'Salafiyah Kecil',
                'deskripsi' => 'Pesantren salaf skala kecil: alur sederhana, libur Jumat, tanpa pajak payroll.',
                'nilai' => [
                    'disiplin.wajib_verifikasi' => false,
                    'disiplin.notif_wali' => 'eskalasi',
                    'kesantrian.maks_izin_bulan' => 1,
                    'kesantrian.approval_h_minimum' => 1,
                    'kepengasuhan.presensi_default_hadir' => true,
                    'akademik.hari_libur_mingguan' => ['jumat'],
                    'akademik.penempatan_mode' => 'bebas',
                    'payroll.thr_formula' => 'nominal_tetap',
                    'keuangan.jenis_lembaga' => 'pesantren',
                    'keuangan.payroll.pph21_persen' => 0,
                ],
            ],
            'modern-multi-lembaga' => [
                'label' => 'Modern Multi-Lembaga',
                'deskripsi' => 'Pesantren modern bermadrasah: verifikasi berlapis, libur Ahad, administrasi rapi.',
                'nilai' => [
                    'disiplin.wajib_verifikasi' => true,
                    'disiplin.notif_wali' => 'semua',
                    'kesantrian.maks_izin_bulan' => 2,
                    'kesantrian.approval_h_minimum' => 3,
                    'kesantrian.moderasi_pesan_perhatian' => true,
                    'kepengasuhan.presensi_default_hadir' => false,
                    'akademik.hari_libur_mingguan' => ['minggu'],
                    'akademik.penempatan_mode' => 'berjenjang',
                    'payroll.thr_formula' => 'kali_honor_pokok',
                    'payroll.thr_pengali' => 1,
                    'keuangan.jenis_lembaga' => 'madrasah',
                ],
            ],
        ];
    }
}
