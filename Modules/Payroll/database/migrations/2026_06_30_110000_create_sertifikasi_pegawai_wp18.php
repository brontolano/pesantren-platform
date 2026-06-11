<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-18 — Arsip Sertifikasi pegawai (TPG/Inpassing/GBPNS/PPG). Data queryable
 * untuk rekap kesiapan EMIS (WP-12 menyusul). File arsip via /_tenant-storage
 * direktori 'sertifikasi' (masuk daftar sensitif di routes/tenant.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sertifikasi_pegawai', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('employee_id')->constrained('employee')->cascadeOnDelete();
            $table->string('jenis', 20);                       // tpg|inpassing|gbpns|ppg (CHECK di bawah)
            $table->string('nrg')->nullable();
            $table->string('nomor_sk');
            $table->decimal('nominal', 15, 2)->nullable();     // nominal tunjangan (bila ada)
            $table->date('berlaku_mulai')->nullable();
            $table->date('berlaku_selesai')->nullable();
            $table->string('file')->nullable();                // arsip pdf/jpg/png
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sertifikasi_pegawai ADD CONSTRAINT sertifikasi_pegawai_jenis_check CHECK (jenis IN ('tpg','inpassing','gbpns','ppg'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sertifikasi_pegawai');
    }
};
