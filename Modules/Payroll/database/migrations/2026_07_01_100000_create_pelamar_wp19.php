<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-19 — Rekrutmen Ringan: tabel pelamar.
 *
 * Pipeline tahap: lamaran → tes_baca_kitab → microteaching → wawancara →
 * diterima | ditolak (CHECK di Postgres, validasi app-level di service).
 * catatan_penilai JSON {tahap: catatan}. employee_id terisi saat dikonversi
 * "Jadikan Pegawai" — penanda idempoten (tak bisa dikonversi 2×).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelamar', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama');
            $table->string('kontak')->nullable();   // HP/WA/email — bebas format
            $table->string('asal')->nullable();     // asal daerah/lembaga
            $table->boolean('is_alumni')->default(false);
            $table->string('berkas_path')->nullable(); // dir 'pelamar' (sensitif — wajib login)
            $table->string('tahap', 20)->default('lamaran');
            $table->json('catatan_penilai')->nullable(); // {tahap: catatan}
            $table->foreignId('employee_id')->nullable()->constrained('employee')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'tahap']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE pelamar ADD CONSTRAINT pelamar_tahap_check CHECK (tahap IN ('lamaran','tes_baca_kitab','microteaching','wawancara','diterima','ditolak'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pelamar');
    }
};
