<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F6 — Reconciliation Engine (bagian Bank Reconciliation).
 * Import mutasi rekening koran → auto-match ke jurnal_baris akun kas/bank.
 * Additif & tenant-scoped; tidak menyentuh tabel uang yang sudah LIVE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('akun_id')->constrained('akun')->restrictOnDelete(); // akun kas/bank
            $table->string('nama');                       // "Rekening BSI - Juni 2026"
            $table->date('periode_awal')->nullable();
            $table->date('periode_akhir')->nullable();
            $table->decimal('saldo_awal', 15, 2)->nullable();
            $table->decimal('saldo_akhir', 15, 2)->nullable();
            $table->string('sumber', 20)->default('manual'); // manual / csv / mt940
            $table->string('status', 12)->default('draft');  // draft / terkunci
            $table->foreignId('diimpor_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'akun_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('bank_statement_line', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('bank_statement_id')->constrained('bank_statement')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('keterangan')->nullable();
            $table->string('referensi')->nullable();        // no. referensi bank
            $table->decimal('nominal', 15, 2);              // selalu positif
            $table->string('arah', 8);                      // masuk (debit kas) / keluar (kredit kas)
            $table->string('status', 12)->default('belum_cocok'); // belum_cocok / cocok
            $table->foreignId('jurnal_baris_id')->nullable()->constrained('jurnal_baris')->nullOnDelete();
            $table->foreignId('dicocokkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dicocokkan_pada')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'bank_statement_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'jurnal_baris_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_line');
        Schema::dropIfExists('bank_statement');
    }
};
