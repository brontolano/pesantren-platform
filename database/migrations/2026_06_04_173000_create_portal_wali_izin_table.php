<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_wali_izin', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->foreignId('wali_id')->constrained('users')->cascadeOnDelete();
            $table->string('jenis_izin', 30);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan');
            $table->text('catatan_wali')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_pada')->nullable();
            $table->text('catatan_admin')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'wali_id', 'status']);
            $table->index(['tenant_id', 'santri_id', 'tanggal_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_wali_izin');
    }
};
