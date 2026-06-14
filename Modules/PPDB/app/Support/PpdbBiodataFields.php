<?php

namespace Modules\PPDB\Support;

/**
 * Sumber kebenaran TUNGGAL field Biodata Peserta SPMB (setara modul Santri +
 * standar SPMB/EMIS). Dipakai bersama oleh:
 *  - PpdbFormFieldConfigurationService  → label + mode default (bisa di-override per tenant)
 *  - PesertaPortalController            → aturan validasi, persist, hitung kelengkapan
 *  - Frontend (Dashboard.vue)           → render form per-section (type/options/label)
 *
 * Kunci field = nama kolom DB = nama field request (snake_case) — tanpa pemetaan
 * camelCase, supaya persist cukup $calon->update($visible). Mode:
 *   required = wajib diisi peserta · optional = opsional peserta ·
 *   hidden   = tidak dipakai       · staf     = diisi panitia (disembunyikan dari peserta)
 */
class PpdbBiodataFields
{
    /** Urutan + judul section form. @var array<string,string> */
    public const SECTIONS = [
        'pribadi' => 'Data Pribadi Calon Santri',
        'alamat' => 'Alamat / Domisili',
        'pendidikan' => 'Riwayat Pendidikan',
        'ayah' => 'Data Ayah',
        'ibu' => 'Data Ibu',
        'wali' => 'Data Wali / Penanggung Jawab',
        'kesehatan' => 'Kesehatan',
    ];

    private const OPT_AGAMA = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];

    private const OPT_JENJANG = ['SD/MI', 'SMP/MTs', 'SMA/MA', 'Paket Kesetaraan'];

    private const OPT_PENDIDIKAN = ['Tidak Sekolah', 'SD/MI', 'SMP/MTs', 'SMA/MA/SMK', 'D1-D3', 'S1/D4', 'S2', 'S3'];

    private const OPT_PENGHASILAN = [
        'Kurang dari Rp1.000.000',
        'Rp1.000.000 - Rp3.000.000',
        'Rp3.000.000 - Rp5.000.000',
        'Rp5.000.000 - Rp10.000.000',
        'Lebih dari Rp10.000.000',
        'Tidak berpenghasilan',
    ];

    private const OPT_HUBUNGAN = ['Ayah Kandung', 'Ibu Kandung', 'Kakek/Nenek', 'Paman/Bibi', 'Kakak', 'Wali Lainnya'];

    private const OPT_GOLDAR = ['A', 'B', 'AB', 'O', 'Tidak tahu'];

    /**
     * Definisi lengkap tiap field.
     *
     * @return array<string, array{label:string, mode:string, section:string, type:string, rules:array<int,string>, options?:array<int,array{value:string,label:string}>, wilayah?:string, placeholder?:string, help?:string}>
     */
    public static function all(): array
    {
        $text = fn (int $max = 255): array => ['string', "max:{$max}"];

        return [
            // ── A. Data Pribadi ──────────────────────────────────────────────
            'nama' => ['label' => 'Nama lengkap calon santri', 'mode' => 'required', 'section' => 'pribadi', 'type' => 'text', 'rules' => $text()],
            'nama_panggilan' => ['label' => 'Nama panggilan', 'mode' => 'optional', 'section' => 'pribadi', 'type' => 'text', 'rules' => $text(100)],
            'nik' => ['label' => 'NIK (16 digit)', 'mode' => 'optional', 'section' => 'pribadi', 'type' => 'text', 'rules' => $text(50), 'placeholder' => '32xxxxxxxxxxxxxx'],
            'nisn' => ['label' => 'NISN', 'mode' => 'staf', 'section' => 'pribadi', 'type' => 'text', 'rules' => $text(50), 'help' => 'Diverifikasi operator dari data EMIS.'],
            'tempat_lahir' => ['label' => 'Tempat lahir', 'mode' => 'required', 'section' => 'pribadi', 'type' => 'text', 'rules' => $text(100)],
            'tanggal_lahir' => ['label' => 'Tanggal lahir', 'mode' => 'required', 'section' => 'pribadi', 'type' => 'date', 'rules' => ['date']],
            'jenis_kelamin' => ['label' => 'Jenis kelamin', 'mode' => 'required', 'section' => 'pribadi', 'type' => 'select', 'rules' => ['in:L,P'], 'options' => [['value' => 'L', 'label' => 'Laki-laki'], ['value' => 'P', 'label' => 'Perempuan']]],
            'agama' => ['label' => 'Agama', 'mode' => 'optional', 'section' => 'pribadi', 'type' => 'select', 'rules' => ['in:'.implode(',', self::OPT_AGAMA)], 'options' => self::opts(self::OPT_AGAMA)],
            'anak_ke' => ['label' => 'Anak ke-', 'mode' => 'optional', 'section' => 'pribadi', 'type' => 'number', 'rules' => ['integer', 'min:1', 'max:30']],
            'jumlah_saudara' => ['label' => 'Jumlah saudara', 'mode' => 'optional', 'section' => 'pribadi', 'type' => 'number', 'rules' => ['integer', 'min:0', 'max:30']],
            'cita_cita' => ['label' => 'Cita-cita', 'mode' => 'optional', 'section' => 'pribadi', 'type' => 'text', 'rules' => $text(100)],

            // ── B. Alamat / Domisili (cascade wilayah) ───────────────────────
            'alamat' => ['label' => 'Alamat (jalan/dusun)', 'mode' => 'required', 'section' => 'alamat', 'type' => 'textarea', 'rules' => $text(500)],
            'rt_rw' => ['label' => 'RT/RW', 'mode' => 'optional', 'section' => 'alamat', 'type' => 'text', 'rules' => $text(20), 'placeholder' => '003/008'],
            'provinsi' => ['label' => 'Provinsi', 'mode' => 'required', 'section' => 'alamat', 'type' => 'wilayah', 'wilayah' => 'provinsi', 'rules' => $text()],
            'kabupaten_kota' => ['label' => 'Kabupaten / Kota', 'mode' => 'required', 'section' => 'alamat', 'type' => 'wilayah', 'wilayah' => 'kabupaten_kota', 'rules' => $text()],
            'kecamatan' => ['label' => 'Kecamatan', 'mode' => 'required', 'section' => 'alamat', 'type' => 'wilayah', 'wilayah' => 'kecamatan', 'rules' => $text()],
            'desa_kelurahan' => ['label' => 'Desa / Kelurahan', 'mode' => 'required', 'section' => 'alamat', 'type' => 'wilayah', 'wilayah' => 'desa_kelurahan', 'rules' => $text()],
            'kode_pos' => ['label' => 'Kode pos', 'mode' => 'optional', 'section' => 'alamat', 'type' => 'text', 'rules' => $text(10), 'placeholder' => '45321'],

            // ── C. Riwayat Pendidikan ────────────────────────────────────────
            'asal_sekolah' => ['label' => 'Nama sekolah asal', 'mode' => 'required', 'section' => 'pendidikan', 'type' => 'text', 'rules' => $text(), 'placeholder' => 'mis. MTs Asy-Syifaa', 'help' => 'Tulis NAMA sekolah, bukan jenjangnya.'],
            'jenjang_asal' => ['label' => 'Jenjang sekolah asal', 'mode' => 'required', 'section' => 'pendidikan', 'type' => 'select', 'rules' => ['in:'.implode(',', self::OPT_JENJANG)], 'options' => self::opts(self::OPT_JENJANG)],
            'npsn' => ['label' => 'NPSN sekolah asal', 'mode' => 'optional', 'section' => 'pendidikan', 'type' => 'text', 'rules' => $text(20)],
            'tahun_lulus' => ['label' => 'Tahun lulus', 'mode' => 'optional', 'section' => 'pendidikan', 'type' => 'text', 'rules' => $text(9), 'placeholder' => '2026'],

            // ── D. Data Ayah ─────────────────────────────────────────────────
            'nama_ayah' => ['label' => 'Nama ayah', 'mode' => 'required', 'section' => 'ayah', 'type' => 'text', 'rules' => $text()],
            'nik_ayah' => ['label' => 'NIK ayah', 'mode' => 'optional', 'section' => 'ayah', 'type' => 'text', 'rules' => $text(50)],
            'pendidikan_ayah' => ['label' => 'Pendidikan terakhir ayah', 'mode' => 'optional', 'section' => 'ayah', 'type' => 'select', 'rules' => ['in:'.implode(',', self::OPT_PENDIDIKAN)], 'options' => self::opts(self::OPT_PENDIDIKAN)],
            'pekerjaan_ayah' => ['label' => 'Pekerjaan ayah', 'mode' => 'optional', 'section' => 'ayah', 'type' => 'text', 'rules' => $text()],
            'penghasilan_ayah' => ['label' => 'Penghasilan ayah / bulan', 'mode' => 'optional', 'section' => 'ayah', 'type' => 'select', 'rules' => ['in:'.implode(',', self::OPT_PENGHASILAN)], 'options' => self::opts(self::OPT_PENGHASILAN)],
            'no_hp_ayah' => ['label' => 'No. HP ayah', 'mode' => 'optional', 'section' => 'ayah', 'type' => 'tel', 'rules' => $text(20)],

            // ── E. Data Ibu ──────────────────────────────────────────────────
            'nama_ibu' => ['label' => 'Nama ibu', 'mode' => 'required', 'section' => 'ibu', 'type' => 'text', 'rules' => $text()],
            'nik_ibu' => ['label' => 'NIK ibu', 'mode' => 'optional', 'section' => 'ibu', 'type' => 'text', 'rules' => $text(50)],
            'pendidikan_ibu' => ['label' => 'Pendidikan terakhir ibu', 'mode' => 'optional', 'section' => 'ibu', 'type' => 'select', 'rules' => ['in:'.implode(',', self::OPT_PENDIDIKAN)], 'options' => self::opts(self::OPT_PENDIDIKAN)],
            'pekerjaan_ibu' => ['label' => 'Pekerjaan ibu', 'mode' => 'optional', 'section' => 'ibu', 'type' => 'text', 'rules' => $text()],
            'penghasilan_ibu' => ['label' => 'Penghasilan ibu / bulan', 'mode' => 'optional', 'section' => 'ibu', 'type' => 'select', 'rules' => ['in:'.implode(',', self::OPT_PENGHASILAN)], 'options' => self::opts(self::OPT_PENGHASILAN)],
            'no_hp_ibu' => ['label' => 'No. HP ibu', 'mode' => 'optional', 'section' => 'ibu', 'type' => 'tel', 'rules' => $text(20)],

            // ── F. Data Wali / Penanggung Jawab ──────────────────────────────
            'nama_wali' => ['label' => 'Nama wali', 'mode' => 'optional', 'section' => 'wali', 'type' => 'text', 'rules' => $text(), 'help' => 'Isi bila penanggung jawab bukan ayah/ibu kandung.'],
            'hubungan_wali' => ['label' => 'Hubungan dengan calon', 'mode' => 'optional', 'section' => 'wali', 'type' => 'select', 'rules' => ['in:'.implode(',', self::OPT_HUBUNGAN)], 'options' => self::opts(self::OPT_HUBUNGAN)],
            'pekerjaan_wali' => ['label' => 'Pekerjaan wali', 'mode' => 'optional', 'section' => 'wali', 'type' => 'text', 'rules' => $text()],
            'penghasilan_wali' => ['label' => 'Penghasilan wali / bulan', 'mode' => 'optional', 'section' => 'wali', 'type' => 'select', 'rules' => ['in:'.implode(',', self::OPT_PENGHASILAN)], 'options' => self::opts(self::OPT_PENGHASILAN)],
            'no_hp_wali' => ['label' => 'No. WhatsApp wali', 'mode' => 'required', 'section' => 'wali', 'type' => 'tel', 'rules' => $text(20), 'help' => 'Nomor ini jadi kunci akun portal & notifikasi.'],
            'email_wali' => ['label' => 'Email wali', 'mode' => 'optional', 'section' => 'wali', 'type' => 'email', 'rules' => ['email', 'max:255']],

            // ── G. Kesehatan ─────────────────────────────────────────────────
            'golongan_darah' => ['label' => 'Golongan darah', 'mode' => 'optional', 'section' => 'kesehatan', 'type' => 'select', 'rules' => ['in:'.implode(',', self::OPT_GOLDAR)], 'options' => self::opts(self::OPT_GOLDAR)],
            'tinggi_badan' => ['label' => 'Tinggi badan (cm)', 'mode' => 'optional', 'section' => 'kesehatan', 'type' => 'number', 'rules' => ['integer', 'min:50', 'max:250']],
            'berat_badan' => ['label' => 'Berat badan (kg)', 'mode' => 'optional', 'section' => 'kesehatan', 'type' => 'number', 'rules' => ['integer', 'min:10', 'max:200']],
            'riwayat_penyakit' => ['label' => 'Riwayat penyakit / alergi', 'mode' => 'optional', 'section' => 'kesehatan', 'type' => 'textarea', 'rules' => $text(1000)],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * Map value→label untuk opsi select.
     *
     * @param  array<int,string>  $values
     * @return array<int,array{value:string,label:string}>
     */
    private static function opts(array $values): array
    {
        return array_map(static fn (string $v): array => ['value' => $v, 'label' => $v], $values);
    }
}
