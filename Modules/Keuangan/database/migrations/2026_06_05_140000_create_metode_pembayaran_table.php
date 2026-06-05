<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metode_pembayaran', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('tipe', 20);
            $table->string('nama');
            $table->string('nomor')->nullable();
            $table->string('atas_nama')->nullable();
            $table->text('instruksi')->nullable();
            $table->string('gambar')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'tipe']);
            $table->index(['tenant_id', 'is_active', 'urutan'], 'metode_pembayaran_tenant_active_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metode_pembayaran');
    }
};
