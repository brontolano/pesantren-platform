<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-28 — Stok Obat UKS.
 *
 * SENGAJA TANPA batch/expiry (pelajaran G2-14: kompleksitas farmasi
 * batch-expiry tidak diperlukan di skala UKS pesantren — cukup stok agregat
 * + ambang menipis). Mutasi append-only sebagai jejak audit stok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obat', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama');
            $table->string('satuan')->default('pcs');
            $table->integer('stok')->default(0);
            $table->integer('ambang_menipis')->default(10);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'nama'], 'uq_obat_nama_per_tenant');
            $table->index(['tenant_id', 'aktif']);
        });

        // Stok TAK BOLEH minus di level DB — gerbang terakhir di bawah lock service.
        DB::statement('ALTER TABLE obat ADD CONSTRAINT chk_obat_stok_non_negatif CHECK (stok >= 0)');

        Schema::create('obat_mutasi', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('obat_id')->constrained('obat')->cascadeOnDelete();
            $table->enum('jenis', ['masuk', 'keluar']);
            $table->integer('jumlah');
            $table->nullableMorphs('ref'); // mis. KunjunganUks saat keluar via register UKS
            $table->string('keterangan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'obat_id']);
        });

        DB::statement('ALTER TABLE obat_mutasi ADD CONSTRAINT chk_obat_mutasi_jumlah_positif CHECK (jumlah > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('obat_mutasi');
        Schema::dropIfExists('obat');
    }
};
