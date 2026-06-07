<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Perlebar semua field alamat ke varchar(255) agar tidak ada truncation
// dari data Excel yang formatnya tidak standar (RT 01 RW 15, dll)
return new class extends Migration
{
    private array $fields = [
        'nik', 'nisn',
        'rt_rw', 'desa_kelurahan', 'kecamatan', 'kabupaten_kota', 'provinsi', 'kode_pos',
        'rt_rw_wali', 'desa_kelurahan_wali', 'kecamatan_wali', 'kabupaten_kota_wali', 'provinsi_wali', 'kode_pos_wali',
    ];

    public function up(): void
    {
        Schema::table('santri', function (Blueprint $table) {
            foreach ($this->fields as $field) {
                $table->string($field, 255)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // Tidak perlu di-revert — cukup biarkan varchar(255)
    }
};
