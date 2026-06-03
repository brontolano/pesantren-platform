<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_santri', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->enum('status', ['aktif', 'cuti', 'lulus', 'keluar', 'dikeluarkan']);
            $table->date('tanggal_status');
            $table->text('alasan')->nullable();
            $table->unsignedInteger('durasi_cuti_hari')->nullable()->comment('Durasi cuti dalam hari, null jika bukan cuti');
            $table->date('tanggal_kembali')->nullable()->comment('Estimasi tanggal kembali dari cuti');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'santri_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_santri');
    }
};
