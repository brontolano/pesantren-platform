<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('santri', function (Blueprint $table) {
            // Identitas tambahan
            $table->string('nik', 16)->nullable()->after('agama')->comment('NIK santri (KTP/KK)');
            $table->string('nisn', 20)->nullable()->after('nik')->comment('NISN (Nomor Induk Siswa Nasional)');

            // Alamat santri (terurai per komponen wilayah)
            $table->string('rt_rw', 10)->nullable()->after('alamat')->comment('RT/RW domisili santri');
            $table->string('desa_kelurahan')->nullable()->after('rt_rw');
            $table->string('kecamatan')->nullable()->after('desa_kelurahan');
            $table->string('kabupaten_kota')->nullable()->after('kecamatan');
            $table->string('provinsi')->nullable()->after('kabupaten_kota');
            $table->string('kode_pos', 5)->nullable()->after('provinsi');

            // Alamat wali (terurai, mungkin berbeda dengan alamat santri)
            $table->string('rt_rw_wali', 10)->nullable()->after('no_hp_wali');
            $table->string('desa_kelurahan_wali')->nullable()->after('rt_rw_wali');
            $table->string('kecamatan_wali')->nullable()->after('desa_kelurahan_wali');
            $table->string('kabupaten_kota_wali')->nullable()->after('kecamatan_wali');
            $table->string('provinsi_wali')->nullable()->after('kabupaten_kota_wali');
            $table->string('kode_pos_wali', 5)->nullable()->after('provinsi_wali');
        });
    }

    public function down(): void
    {
        Schema::table('santri', function (Blueprint $table) {
            $table->dropColumn([
                'nik', 'nisn',
                'rt_rw', 'desa_kelurahan', 'kecamatan', 'kabupaten_kota', 'provinsi', 'kode_pos',
                'rt_rw_wali', 'desa_kelurahan_wali', 'kecamatan_wali', 'kabupaten_kota_wali', 'provinsi_wali', 'kode_pos_wali',
            ]);
        });
    }
};
