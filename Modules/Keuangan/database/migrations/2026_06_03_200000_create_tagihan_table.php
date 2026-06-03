<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->string('jenis_tagihan', 100); // spp, infaq, seragam, daftar_ulang, dll
            $table->string('keterangan')->nullable();
            $table->decimal('nominal', 15, 2);
            $table->decimal('diskon', 15, 2)->default(0);
            $table->decimal('nominal_akhir', 15, 2); // nominal - diskon
            $table->date('jatuh_tempo');
            $table->string('status', 20)->default('belum_bayar'); // belum_bayar, sebagian, lunas, batal
            $table->string('periode_akademik', 20)->nullable(); // 2026/2027
            $table->string('bulan_masehi', 7)->nullable(); // 2026-06
            $table->string('bulan_hijriah', 50)->nullable(); // Dzulhijjah 1447
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'santri_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'jatuh_tempo']);
            $table->index(['tenant_id', 'jenis_tagihan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan');
    }
};
