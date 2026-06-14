<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biodata calon santri LENGKAP (setara modul Santri + standar SPMB/EMIS).
 * Additif & nullable — kolom lama (nama, tanggal_lahir, nama_ayah, alamat, dst)
 * dipertahankan. Data ayah/ibu/wali dipisah, masing-masing dengan pekerjaan,
 * penghasilan, pendidikan, NIK, no HP. Alamat santri diurai (cascade wilayah).
 * Semua diisi peserta di portal /spmb (kecuali field bermode 'staf').
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calon_santri')) {
            return;
        }

        Schema::table('calon_santri', function (Blueprint $table): void {
            // A. Data pribadi tambahan
            $this->addString($table, 'nama_panggilan', 255);
            $this->addString($table, 'nik', 50);
            $this->addString($table, 'nisn', 50);
            $this->addString($table, 'agama', 50);
            $this->addString($table, 'cita_cita', 255);
            $this->addString($table, 'photo_url', 255);
            if (! Schema::hasColumn('calon_santri', 'anak_ke')) {
                $table->unsignedSmallInteger('anak_ke')->nullable();
            }
            if (! Schema::hasColumn('calon_santri', 'jumlah_saudara')) {
                $table->unsignedSmallInteger('jumlah_saudara')->nullable();
            }

            // B. Alamat / domisili santri (cascade wilayah, mirror modul Santri)
            // 'alamat' (text) sudah ada dari migrasi awal.
            $this->addString($table, 'rt_rw', 255);
            $this->addString($table, 'desa_kelurahan', 255);
            $this->addString($table, 'kecamatan', 255);
            $this->addString($table, 'kabupaten_kota', 255);
            $this->addString($table, 'provinsi', 255);
            $this->addString($table, 'kode_pos', 255);

            // C. Riwayat pendidikan ('asal_sekolah' = nama sekolah, sudah ada)
            $this->addString($table, 'jenjang_asal', 50);
            $this->addString($table, 'npsn', 20);
            $this->addString($table, 'tahun_lulus', 9);

            // D. Data ayah ('nama_ayah' sudah ada)
            $this->addString($table, 'nik_ayah', 50);
            $this->addString($table, 'pendidikan_ayah', 50);
            $this->addString($table, 'pekerjaan_ayah', 255);
            $this->addString($table, 'penghasilan_ayah', 50);
            $this->addString($table, 'no_hp_ayah', 20);

            // E. Data ibu ('nama_ibu' sudah ada)
            $this->addString($table, 'nik_ibu', 50);
            $this->addString($table, 'pendidikan_ibu', 50);
            $this->addString($table, 'pekerjaan_ibu', 255);
            $this->addString($table, 'penghasilan_ibu', 50);
            $this->addString($table, 'no_hp_ibu', 20);

            // F. Data wali ('no_hp_wali', 'email_wali' sudah ada = akun portal)
            $this->addString($table, 'nama_wali', 255);
            $this->addString($table, 'hubungan_wali', 100);
            $this->addString($table, 'pekerjaan_wali', 255);
            $this->addString($table, 'penghasilan_wali', 50);

            // G. Kesehatan
            $this->addString($table, 'golongan_darah', 3);
            if (! Schema::hasColumn('calon_santri', 'tinggi_badan')) {
                $table->unsignedSmallInteger('tinggi_badan')->nullable();
            }
            if (! Schema::hasColumn('calon_santri', 'berat_badan')) {
                $table->unsignedSmallInteger('berat_badan')->nullable();
            }
            if (! Schema::hasColumn('calon_santri', 'riwayat_penyakit')) {
                $table->text('riwayat_penyakit')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('calon_santri')) {
            return;
        }

        $cols = [
            'nama_panggilan', 'nik', 'nisn', 'agama', 'cita_cita', 'photo_url', 'anak_ke', 'jumlah_saudara',
            'rt_rw', 'desa_kelurahan', 'kecamatan', 'kabupaten_kota', 'provinsi', 'kode_pos',
            'jenjang_asal', 'npsn', 'tahun_lulus',
            'nik_ayah', 'pendidikan_ayah', 'pekerjaan_ayah', 'penghasilan_ayah', 'no_hp_ayah',
            'nik_ibu', 'pendidikan_ibu', 'pekerjaan_ibu', 'penghasilan_ibu', 'no_hp_ibu',
            'nama_wali', 'hubungan_wali', 'pekerjaan_wali', 'penghasilan_wali',
            'golongan_darah', 'tinggi_badan', 'berat_badan', 'riwayat_penyakit',
        ];

        Schema::table('calon_santri', function (Blueprint $table) use ($cols): void {
            foreach ($cols as $col) {
                if (Schema::hasColumn('calon_santri', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function addString(Blueprint $table, string $col, int $len): void
    {
        if (! Schema::hasColumn('calon_santri', $col)) {
            $table->string($col, $len)->nullable();
        }
    }
};
