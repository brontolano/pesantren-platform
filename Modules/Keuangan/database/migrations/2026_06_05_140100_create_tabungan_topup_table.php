<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabungan_topup', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('tabungan_id')->constrained('tabungan')->cascadeOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->date('tanggal_transfer');
            $table->string('nama_pengirim');
            $table->string('bukti');
            $table->string('status', 20)->default('menunggu');
            $table->text('alasan_penolakan')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'tabungan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabungan_topup');
    }
};
