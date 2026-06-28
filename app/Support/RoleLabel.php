<?php

namespace App\Support;

/**
 * Satu sumber label RAMAH untuk role Spatie (slug teknis → bahasa staf pesantren).
 * Dipakai di form/tabel User (dropdown Role) dan GreetingBanner (sapaan sadar-peran).
 * Label hanya untuk TAMPILAN — nilai/izin tetap pakai slug role.
 */
final class RoleLabel
{
    /** @var array<string, string> */
    public const LABELS = [
        // Internal — admin & pimpinan
        'super_admin' => 'Super Admin (akses penuh / pemilik)',
        'admin_pesantren' => 'Admin Pesantren (administrator)',
        'kepala' => 'Kepala / Kyai (pimpinan)',
        'IT' => 'IT / Admin Teknis',
        'tu' => 'Tata Usaha (administrasi)',
        // Pengajar
        'guru' => 'Guru (mata pelajaran)',
        'guru_tahfidz' => 'Guru Tahfidz / Musyrif',
        'asatidz' => 'Ustadz / Asatidz (pengajar kitab)',
        // Keuangan
        'bendahara' => 'Bendahara Unit (tagihan/tabungan/kas)',
        'bendahara_pusat' => 'Bendahara Pusat / Yayasan',
        'kepala_keuangan' => 'Kepala Keuangan',
        'akuntan' => 'Akuntan / Tata Buku (pembukuan)',
        'petugas_payroll' => 'Petugas Payroll / Bisyaroh',
        'kasir' => 'Kasir (kantin / POS / setor)',
        'operator_divisi' => 'Operator Divisi / Unit',
        'dewan_pembina' => 'Dewan Pembina (pengawas)',
        // Kepengasuhan & spesialis
        'konselor' => 'Konselor / Guru BK',
        'pengelola_uks' => 'Petugas UKS / Kesehatan',
        'pengelola_asrama' => 'Pengasuh Asrama / Musyrif',
        'panitia_ppdb' => 'Panitia PPDB / Pendaftaran',
        'pengelola_sarpras' => 'Petugas Sarpras / Aset',
        // Eksternal / komunitas
        'wali' => 'Wali Santri',
        'santri' => 'Santri',
        'jemaah' => 'Jemaah',
        'peserta' => 'Calon Santri (PPDB)',
    ];

    /**
     * Label ramah untuk satu slug role. Fallback: slug di-humanize
     * ("kepala_keuangan" → "Kepala Keuangan").
     */
    public static function for(?string $role): string
    {
        if ($role === null || $role === '') {
            return '';
        }

        return self::LABELS[$role] ?? ucwords(str_replace('_', ' ', $role));
    }
}
