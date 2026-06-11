<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-06 — Kurikulum per Tingkat.
 *
 * `kurikulum`       : payung kurikulum per lembaga (1 lembaga boleh punya
 *                     beberapa kurikulum, hanya yang `aktif` dipakai validasi).
 * `kurikulum_mapel` : baris mapel per tingkat (tingkat = string, konvensi
 *                     sama dengan kelas.tingkat: "7", "10", "1 Ibtidaiyah").
 *                     kitab_id opsional (mapel diniyah berbasis kitab).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kurikulum', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->string('nama');
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'lembaga_id']);
            $table->unique(['tenant_id', 'lembaga_id', 'nama']);
        });

        Schema::create('kurikulum_mapel', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('kurikulum_id')->constrained('kurikulum')->cascadeOnDelete();
            $table->foreignId('pelajaran_id')->constrained('pelajaran')->cascadeOnDelete();
            $table->string('tingkat'); // konvensi kelas.tingkat (string bebas)
            $table->foreignId('kitab_id')->nullable()->constrained('kitab')->nullOnDelete();
            $table->unsignedSmallInteger('jam_per_minggu')->default(2);
            $table->boolean('is_wajib')->default(true);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'kurikulum_id', 'tingkat']);
            $table->index(['tenant_id', 'tingkat']);
            $table->unique(['kurikulum_id', 'pelajaran_id', 'tingkat'], 'kurikulum_mapel_kurikulum_pelajaran_tingkat_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kurikulum_mapel');
        Schema::dropIfExists('kurikulum');
    }
};
