<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-02 — Kalender akademik + hari efektif (Blueprint Akademik A-0 poin 3).
 *
 * Entri rentang tanggal per tenant; lembaga_id NULL = berlaku SEMUA lembaga
 * (pola WP-01). Jenis libur_nasional|libur_pondok|ramadhan mengurangi hari
 * efektif; ujian|pasaran|kegiatan TIDAK (hanya penanda agenda).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kalender_akademik', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->nullOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('jenis', 30); // libur_nasional|libur_pondok|ramadhan|ujian|pasaran|kegiatan
            $table->string('keterangan');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'tanggal_mulai', 'tanggal_selesai'], 'kalender_akademik_tenant_rentang_index');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE kalender_akademik ADD CONSTRAINT kalender_akademik_jenis_check CHECK (jenis IN ('libur_nasional','libur_pondok','ramadhan','ujian','pasaran','kegiatan'))");
            DB::statement('ALTER TABLE kalender_akademik ADD CONSTRAINT kalender_akademik_rentang_check CHECK (tanggal_selesai >= tanggal_mulai)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kalender_akademik');
    }
};
