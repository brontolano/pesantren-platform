<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2-13 — Jadwal penyusutan per aset per periode (garis lurus / saldo menurun).
 * Tiap baris = beban 1 periode; posting jurnal idempoten (jurnal_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jadwal_penyusutan')) {
            return;
        }

        Schema::create('jadwal_penyusutan', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('aset_id');
            $table->unsignedInteger('urutan');           // ke-1, ke-2, ...
            $table->string('periode', 7);                // YYYY-MM
            $table->decimal('nilai_awal', 18, 2)->default(0);
            $table->decimal('beban_penyusutan', 18, 2)->default(0);
            $table->decimal('akumulasi', 18, 2)->default(0);
            $table->decimal('nilai_akhir', 18, 2)->default(0); // nilai buku setelah periode ini
            $table->string('status')->default('draft');  // draft | posted
            $table->unsignedBigInteger('jurnal_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'aset_id']);
            $table->unique(['tenant_id', 'aset_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_penyusutan');
    }
};
