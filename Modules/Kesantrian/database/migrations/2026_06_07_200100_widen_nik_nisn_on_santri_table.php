<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('santri', function (Blueprint $table) {
            $table->string('nik', 30)->nullable()->change();
            $table->string('nisn', 50)->nullable()->change();
            $table->string('rt_rw', 20)->nullable()->change();
            $table->string('kode_pos', 10)->nullable()->change();
            $table->string('kode_pos_wali', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('santri', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->change();
            $table->string('nisn', 20)->nullable()->change();
            $table->string('rt_rw', 10)->nullable()->change();
            $table->string('kode_pos', 5)->nullable()->change();
            $table->string('kode_pos_wali', 5)->nullable()->change();
        });
    }
};
