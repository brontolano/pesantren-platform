<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calon_santri', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('nomor_pendaftaran', 50);
            $table->string('nama');
            $table->date('tanggal_lahir');
            $table->string('tempat_lahir')->nullable();
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->string('nama_ayah')->nullable();
            $table->string('nama_ibu')->nullable();
            $table->string('no_hp_wali')->nullable();
            $table->string('email_wali')->nullable();
            $table->text('alamat')->nullable();
            $table->string('asal_sekolah')->nullable();
            $table->string('status_pendaftaran', 30)->default('draft');
            $table->json('data_ortu')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'nomor_pendaftaran']);
            $table->index(['tenant_id', 'status_pendaftaran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calon_santri');
    }
};
