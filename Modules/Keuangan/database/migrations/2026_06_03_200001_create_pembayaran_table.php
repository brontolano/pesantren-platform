<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('tagihan_id')->constrained('tagihan')->cascadeOnDelete();
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->string('metode', 50)->default('tunai'); // tunai, transfer, qris
            $table->date('tanggal_pembayaran');
            $table->string('nomor_bukti')->nullable();
            $table->string('bukti_pembayaran')->nullable(); // file path
            $table->text('catatan')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'tagihan_id']);
            $table->index(['tenant_id', 'santri_id']);
            $table->index(['tenant_id', 'tanggal_pembayaran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};
