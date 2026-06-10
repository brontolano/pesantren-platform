<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('penempatan_kelas')) {
            return;
        }

        Schema::create('penempatan_kelas', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->string('semester')->nullable()->comment('e.g. Ganjil, Genap');
            $table->string('tahun_akademik', 9)->comment('e.g. 2026/2027');
            $table->date('tanggal_penempatan');
            $table->string('alasan_mutasi')->nullable()->comment('null = penempatan awal');
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'santri_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penempatan_kelas');
    }
};
