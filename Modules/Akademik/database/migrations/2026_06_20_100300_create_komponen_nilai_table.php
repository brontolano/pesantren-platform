<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F0-PWA — Bobot nilai konfigurabel per tenant/pelajaran.
 *
 * komponen_nilai.pelajaran_id NULL = default tenant; terisi = override per pelajaran.
 * ALTER nilai + JSON nilai_komponen BERDAMPINGAN dengan kolom lama
 * (nilai_harian/uts/uas JANGAN dihapus — rapor & laporan lama membacanya).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('komponen_nilai', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('pelajaran_id')->nullable()->constrained('pelajaran')->cascadeOnDelete();
            $table->string('nama');
            $table->unsignedSmallInteger('bobot'); // persen
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'pelajaran_id']);
        });

        Schema::table('nilai', function (Blueprint $table): void {
            $table->json('nilai_komponen')->nullable();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE komponen_nilai ADD CONSTRAINT komponen_nilai_bobot_check CHECK (bobot BETWEEN 0 AND 100)');
            // Unique dengan pelajaran_id nullable: partial index per kombinasi.
            DB::statement('CREATE UNIQUE INDEX komponen_nilai_default_nama_unique ON komponen_nilai (tenant_id, nama) WHERE pelajaran_id IS NULL');
            DB::statement('CREATE UNIQUE INDEX komponen_nilai_pelajaran_nama_unique ON komponen_nilai (tenant_id, pelajaran_id, nama) WHERE pelajaran_id IS NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('nilai', function (Blueprint $table): void {
            $table->dropColumn('nilai_komponen');
        });
        Schema::dropIfExists('komponen_nilai');
    }
};
