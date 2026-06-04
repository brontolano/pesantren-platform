<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_kesehatan', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('keluhan')->nullable();
            $table->string('diagnosa')->nullable();
            $table->text('tindakan')->nullable();
            $table->string('kondisi')->default('baik'); // baik, sakit, rujuk
            $table->string('petugas')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'santri_id']);
            $table->index(['tenant_id', 'tanggal']);
        });

        Schema::create('sesi_konseling', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('topik')->nullable();
            $table->text('deskripsi')->nullable();
            $table->text('rekomendasi')->nullable();
            $table->string('status')->default('terjadwal'); // terjadwal, selesai, dibatalkan
            $table->foreignId('konselor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan_lanjutan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'santri_id']);
            $table->index(['tenant_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_konseling');
        Schema::dropIfExists('riwayat_kesehatan');
    }
};
