<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabungan_transaksi', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('tabungan_id')->constrained('tabungan')->cascadeOnDelete();
            $table->string('tipe', 10); // setor, tarik
            $table->decimal('nominal', 15, 2);
            $table->decimal('saldo_sebelum', 15, 2);
            $table->decimal('saldo_sesudah', 15, 2);
            $table->string('keterangan')->nullable();
            $table->date('tanggal');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'tabungan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabungan_transaksi');
    }
};
