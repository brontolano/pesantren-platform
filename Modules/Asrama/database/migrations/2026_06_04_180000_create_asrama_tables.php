<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blok_asrama', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama_blok');
            $table->string('lokasi')->nullable();
            $table->unsignedInteger('jumlah_kamar')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'nama_blok']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('kamar_asrama', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('blok_id')->constrained('blok_asrama')->cascadeOnDelete();
            $table->string('nomor_kamar');
            $table->unsignedInteger('kapasitas')->default(1);
            $table->enum('tipe', ['regular', 'vip', 'khusus'])->default('regular');
            $table->decimal('biaya_per_bulan', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'blok_id', 'nomor_kamar']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('penempatan_asrama', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->foreignId('kamar_id')->constrained('kamar_asrama')->cascadeOnDelete();
            $table->date('tanggal_masuk');
            $table->date('tanggal_keluar')->nullable();
            $table->string('status')->default('aktif');
            $table->string('keterangan')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'santri_id', 'is_current']);
            $table->index(['tenant_id', 'kamar_id', 'is_current']);
        });

        Schema::create('biaya_asrama', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->foreignId('kamar_id')->nullable()->constrained('kamar_asrama')->nullOnDelete();
            $table->string('periode');
            $table->decimal('nominal', 14, 2);
            $table->enum('status_pembayaran', ['belum_bayar', 'sebagian', 'lunas'])->default('belum_bayar');
            $table->foreignId('keuangan_tagihan_id')->nullable()->constrained('tagihan')->nullOnDelete();
            $table->date('jatuh_tempo')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'santri_id', 'periode']);
            $table->index(['tenant_id', 'status_pembayaran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biaya_asrama');
        Schema::dropIfExists('penempatan_asrama');
        Schema::dropIfExists('kamar_asrama');
        Schema::dropIfExists('blok_asrama');
    }
};
