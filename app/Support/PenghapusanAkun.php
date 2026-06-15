<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Petunjuk Penghapusan Akun & Data — WAJIB Google Play (Data deletion /
 * account deletion URL). Redaksi default disediakan platform, bisa diedit per
 * pesantren (Setting 'penghapusan_akun_sistem'). Dipublikasi di /hapus-akun
 * (domain tenant), ditautkan dari Kebijakan Privasi, dan dipakai sebagai URL
 * "Penghapusan Akun" pada formulir Data safety Play Console.
 *
 * Sengaja generik untuk SEMUA aplikasi tenant (SPMB/peserta, Portal Wali,
 * aplikasi santri, guru) sehingga satu halaman melayani seluruh app yang akan
 * dirilis ke Play Store. Pola identik dengan {@see KebijakanPrivasiSistem}.
 */
class PenghapusanAkun
{
    public const SETTING_KEY = 'penghapusan_akun_sistem';

    public static function isi(): string
    {
        $custom = Setting::getTenantStringValue(self::SETTING_KEY);
        if (is_string($custom) && trim($custom) !== '') {
            return trim($custom);
        }

        return self::default();
    }

    public static function default(): string
    {
        $nama = TenantBranding::name(tenant('name') ?? 'Pesantren');

        return <<<TEKS
PENGHAPUSAN AKUN & DATA — {$nama}

Halaman ini menjelaskan cara meminta penghapusan akun dan data pribadi Anda pada aplikasi {$nama} (termasuk aplikasi Penerimaan Santri Baru/SPMB, Portal Wali, dan aplikasi santri yang dikelola pesantren).

1. SIAPA YANG DAPAT MENGAJUKAN
Permintaan diajukan oleh pemilik akun: orang tua/wali, calon santri/peserta, atau santri yang bersangkutan. Untuk data anak di bawah 18 tahun, permintaan diajukan oleh orang tua/wali.

2. CARA MENGAJUKAN PENGHAPUSAN
Sampaikan permintaan melalui salah satu kanal resmi pesantren yang tercantum di bawah (WhatsApp admin, email, atau datang langsung ke kantor pesantren). Sertakan: nama lengkap, nomor pendaftaran / NIS / email akun, dan hubungan Anda dengan santri. Petugas memverifikasi identitas sebelum memproses agar data tidak dihapus oleh pihak yang tidak berhak.

3. DATA YANG DIHAPUS
Setelah permintaan disetujui dan identitas terverifikasi, kami menghapus: akun login (nama pengguna & sandi), data profil pribadi (nama, kontak, alamat, foto, tanda tangan), serta contoh tulisan tangan yang Anda kirim.

4. DATA YANG DISIMPAN SEMENTARA (RETENSI)
Sebagian data administrasi pendidikan dan keuangan (mis. riwayat pembayaran, arsip akademik) dapat disimpan untuk jangka waktu tertentu selama diwajibkan peraturan, lalu dihapus permanen. Data yang tidak wajib disimpan dihapus selambat-lambatnya 30 hari sejak permintaan disetujui; data yang wajib diarsipkan disimpan paling lama 90 hari setelah kewajiban berakhir, kecuali peraturan menentukan lain.

5. WAKTU PEMROSESAN
Permintaan diproses dalam 7 hari kerja. Anda diberi tahu setelah penghapusan selesai.

6. MENARIK PERSETUJUAN
Anda berhak menarik persetujuan penggunaan contoh tulisan tangan kapan saja tanpa memengaruhi layanan administrasi pokok.

Untuk pertanyaan atau pengajuan, hubungi kantor pesantren melalui kanal yang tertera pada halaman ini.
TEKS;
    }
}
