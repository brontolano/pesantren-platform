<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('santri', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('nis')->comment('Nomor Induk Santri — unique per tenant');
            $table->string('nama');
            $table->date('tanggal_lahir');
            $table->string('tempat_lahir')->nullable();
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->string('agama')->default('Islam');
            $table->string('asal_sekolah')->nullable();
            $table->text('alamat')->nullable();
            $table->string('no_hp_wali')->nullable();
            $table->string('email_wali')->nullable();
            $table->string('nama_ayah')->nullable();
            $table->string('nama_ibu')->nullable();
            $table->string('pekerjaan_ayah')->nullable();
            $table->string('pekerjaan_ibu')->nullable();
            $table->enum('status', ['aktif', 'cuti', 'lulus', 'keluar', 'dikeluarkan'])->default('aktif');
            $table->string('tahun_angkatan', 9)->nullable()->comment('e.g. 2026/2027');
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->string('photo_url')->nullable();
            $table->foreignId('wali_user_id')->nullable()->constrained('users')->nullOnDelete()->comment('Link ke akun wali di portal');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->unique(['tenant_id', 'nis']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'kelas_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('santri');
    }
};
