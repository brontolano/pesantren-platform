<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akun', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('kode', 20);                       // 1-1000 Kas, 2-1100 Utang Pajak
            $table->string('nama');
            $table->string('tipe', 20);                       // ASET / KEWAJIBAN / EKUITAS / PENDAPATAN / BEBAN
            $table->string('saldo_normal', 10);               // debit / kredit
            $table->foreignId('parent_id')->nullable()->constrained('akun')->nullOnDelete(); // hierarki COA
            $table->boolean('is_kas_bank')->default(false);   // penanda akun kas/bank (BKU & Arus Kas)
            $table->boolean('is_postable')->default(true);    // akun induk biasanya tidak boleh diposting
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'kode']);
            $table->index(['tenant_id', 'tipe']);
            $table->index(['tenant_id', 'is_kas_bank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akun');
    }
};
