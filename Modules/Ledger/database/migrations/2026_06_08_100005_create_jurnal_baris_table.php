<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal_baris', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('jurnal_id')->constrained('jurnal')->cascadeOnDelete();
            // Tiga dimensi: akun (APA) · divisi_unit (DI MANA) · sumber_dana (UANG SIAPA)
            $table->foreignId('akun_id')->constrained('akun')->restrictOnDelete();
            $table->foreignId('divisi_id')->nullable()->constrained('divisi_unit')->nullOnDelete();
            $table->foreignId('sumber_dana_id')->nullable()->constrained('sumber_dana')->nullOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'jurnal_id']);
            $table->index(['tenant_id', 'akun_id']);
            $table->index(['tenant_id', 'sumber_dana_id']);
            $table->index(['tenant_id', 'divisi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_baris');
    }
};
