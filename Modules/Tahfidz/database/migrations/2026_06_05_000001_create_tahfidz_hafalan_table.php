<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahfidz_hafalan', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->string('surah')->nullable();
            $table->unsignedInteger('ayat_awal')->nullable();
            $table->unsignedInteger('ayat_akhir')->nullable();
            $table->date('tanggal_target')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('status')->default('direncanakan');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'santri_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahfidz_hafalan');
    }
};
