<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Kebijakan Privasi Sistem Pondok — redaksi default disediakan platform,
 * bisa diedit per pesantren (Setting 'kebijakan_privasi_sistem'). Dipublikasi
 * di /kebijakan-privasi (domain tenant) & disetujui via checkbox saat daftar
 * PPDB (UU PDP 27/2022 — data anak & biometrik = data pribadi spesifik).
 */
class KebijakanPrivasiSistem
{
    public const SETTING_KEY = 'kebijakan_privasi_sistem';

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
KEBIJAKAN PRIVASI SISTEM INFORMASI {$nama}

1. DATA YANG KAMI KUMPULKAN
Untuk keperluan administrasi pendidikan, kami mengumpulkan: data identitas santri dan orang tua/wali (nama, tanggal lahir, alamat, nomor HP), foto santri, tanda tangan, serta contoh tulisan tangan santri (Arab dan Indonesia).

2. TUJUAN PENGGUNAAN
- Administrasi kesantrian: kartu santri, absensi, pembayaran, rapor, dan komunikasi dengan wali melalui WhatsApp.
- Contoh tulisan tangan digunakan HANYA untuk pengembangan teknologi internal pesantren yang membantu ustadz membaca dan menilai lembar jawaban santri. Pemrosesan dilakukan di server pesantren sendiri (tidak dikirim ke pihak ketiga/layanan cloud), dan TIDAK dipakai untuk pesantren lain.

3. PENYIMPANAN & KEAMANAN
Data tersimpan di basis data terpisah milik pesantren dengan pencadangan harian. Akses dibatasi hanya untuk pengurus yang berwenang.

4. HAK ORANG TUA/WALI
Orang tua/wali berhak melihat, memperbaiki, atau meminta penghapusan data anaknya — termasuk menarik persetujuan penggunaan contoh tulisan tangan — dengan menghubungi kantor pesantren. Penarikan persetujuan tidak memengaruhi layanan administrasi pokok.

5. PERSETUJUAN
Dengan mencentang kotak persetujuan saat pendaftaran, orang tua/wali menyatakan telah membaca dan menyetujui kebijakan ini.

Kebijakan ini dapat diperbarui; versi terbaru selalu tersedia di halaman ini.
TEKS;
    }
}
