<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parity Santri ⟷ Biodata SPMB: kolom EMIS / data orang tua yang belum ada di
 * tabel `santri`, agar seluruh data calon ikut terbawa saat daftar ulang
 * (lulus → Santri). Additif & nullable; kolom yang sudah ada dilewati.
 * (nik, nisn, agama, pekerjaan_ayah/ibu, no_hp_ayah/ibu, alamat berjenjang,
 *  no_hp_wali, email_wali sudah ada dari migrasi sebelumnya.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('santri')) {
            return;
        }

        Schema::table('santri', function (Blueprint $table): void {
            // Pribadi tambahan
            $this->addString($table, 'nama_panggilan', 255);
            $this->addString($table, 'cita_cita', 255);
            if (! Schema::hasColumn('santri', 'anak_ke')) {
                $table->unsignedSmallInteger('anak_ke')->nullable();
            }
            if (! Schema::hasColumn('santri', 'jumlah_saudara')) {
                $table->unsignedSmallInteger('jumlah_saudara')->nullable();
            }

            // Riwayat pendidikan asal
            $this->addString($table, 'jenjang_asal', 50);
            $this->addString($table, 'npsn', 20);
            $this->addString($table, 'tahun_lulus', 9);

            // Data ayah lengkap
            $this->addString($table, 'nik_ayah', 50);
            $this->addString($table, 'pendidikan_ayah', 50);
            $this->addString($table, 'penghasilan_ayah', 50);

            // Data ibu lengkap
            $this->addString($table, 'nik_ibu', 50);
            $this->addString($table, 'pendidikan_ibu', 50);
            $this->addString($table, 'penghasilan_ibu', 50);

            // Data wali (penanggung jawab)
            $this->addString($table, 'nama_wali', 255);
            $this->addString($table, 'hubungan_wali', 100);
            $this->addString($table, 'pekerjaan_wali', 255);
            $this->addString($table, 'penghasilan_wali', 50);

            // Kesehatan
            $this->addString($table, 'golongan_darah', 3);
            if (! Schema::hasColumn('santri', 'tinggi_badan')) {
                $table->unsignedSmallInteger('tinggi_badan')->nullable();
            }
            if (! Schema::hasColumn('santri', 'berat_badan')) {
                $table->unsignedSmallInteger('berat_badan')->nullable();
            }
            if (! Schema::hasColumn('santri', 'riwayat_penyakit')) {
                $table->text('riwayat_penyakit')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('santri')) {
            return;
        }

        $cols = [
            'nama_panggilan', 'cita_cita', 'anak_ke', 'jumlah_saudara',
            'jenjang_asal', 'npsn', 'tahun_lulus',
            'nik_ayah', 'pendidikan_ayah', 'penghasilan_ayah',
            'nik_ibu', 'pendidikan_ibu', 'penghasilan_ibu',
            'nama_wali', 'hubungan_wali', 'pekerjaan_wali', 'penghasilan_wali',
            'golongan_darah', 'tinggi_badan', 'berat_badan', 'riwayat_penyakit',
        ];

        Schema::table('santri', function (Blueprint $table) use ($cols): void {
            foreach ($cols as $col) {
                if (Schema::hasColumn('santri', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function addString(Blueprint $table, string $col, int $len): void
    {
        if (! Schema::hasColumn('santri', $col)) {
            $table->string($col, $len)->nullable();
        }
    }
};
