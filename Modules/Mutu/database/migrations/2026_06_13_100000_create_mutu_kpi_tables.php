<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G3-17 — SPMI/Quality (acuan KMA 939): sasaran/KPI, inspeksi/audit, temuan
 * (Non-Conformance) + tindakan korektif, dan SKM kepuasan wali. Additif, guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mutu_sasaran')) {
            Schema::create('mutu_sasaran', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('kode', 50)->nullable();
                $table->string('nama');
                $table->string('area', 30)->default('umum'); // akademik|tahfidz|layanan|umum
                $table->string('satuan', 30)->nullable();
                $table->decimal('target', 15, 2)->default(0);
                $table->decimal('realisasi', 15, 2)->default(0);
                $table->string('periode', 20)->nullable(); // 2026 / 2026-S1
                $table->string('regulasi_acuan')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'area']);
            });
        }

        if (! Schema::hasTable('mutu_inspeksi')) {
            Schema::create('mutu_inspeksi', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('judul');
                $table->string('area', 30)->default('umum');
                $table->date('tanggal')->nullable();
                $table->string('auditor')->nullable();
                $table->text('ringkasan')->nullable();
                $table->string('status', 20)->default('draft'); // draft|selesai
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'area']);
            });
        }

        if (! Schema::hasTable('mutu_temuan')) {
            Schema::create('mutu_temuan', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->foreignId('inspeksi_id')->nullable()->constrained('mutu_inspeksi')->nullOnDelete();
                $table->foreignId('sasaran_id')->nullable()->constrained('mutu_sasaran')->nullOnDelete();
                $table->string('judul');
                $table->text('deskripsi')->nullable();
                $table->string('severity', 20)->default('minor');     // minor|major|kritis
                $table->unsignedTinyInteger('severity_level')->default(1); // 1|2|3 (untuk ApprovalRule)
                $table->string('status', 20)->default('terbuka');     // terbuka|tindakan|verifikasi|tutup
                $table->text('tindakan_korektif')->nullable();
                $table->unsignedBigInteger('pic_id')->nullable();
                $table->date('tenggat')->nullable();
                $table->unsignedBigInteger('diverifikasi_oleh')->nullable();
                $table->timestamp('diverifikasi_pada')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('mutu_skm')) {
            Schema::create('mutu_skm', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('periode', 20)->nullable();
                $table->string('aspek')->nullable();        // layanan|akademik|sarana|umum
                $table->string('responden')->nullable();
                $table->unsignedTinyInteger('skor')->default(0); // 1..5
                $table->text('komentar')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'periode']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mutu_temuan');
        Schema::dropIfExists('mutu_skm');
        Schema::dropIfExists('mutu_inspeksi');
        Schema::dropIfExists('mutu_sasaran');
    }
};
