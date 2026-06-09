<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel anggaran (RKAM) — pagu belanja per akun + dimensi + tahun.
 * Dipakai AnggaranGuardService untuk membandingkan realisasi vs pagu
 * (peringatan/blokir) saat belanja disetujui. Additif, tenant-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anggaran', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('tahun_anggaran', 9); // mis. "2026" (tahun kalender realisasi)
            $table->foreignId('akun_id')->constrained('akun')->cascadeOnDelete();
            $table->foreignId('divisi_id')->nullable()->constrained('divisi_unit')->nullOnDelete();
            $table->foreignId('sumber_dana_id')->nullable()->constrained('sumber_dana')->nullOnDelete();
            $table->decimal('nominal_anggaran', 15, 2)->default(0);
            $table->enum('mode', ['peringatan', 'blokir'])->default('peringatan');
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'tahun_anggaran']);
            $table->unique(
                ['tenant_id', 'tahun_anggaran', 'akun_id', 'divisi_id', 'sumber_dana_id'],
                'anggaran_unik'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anggaran');
    }
};
