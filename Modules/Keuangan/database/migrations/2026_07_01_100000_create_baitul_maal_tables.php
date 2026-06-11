<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2c — Baitul Maal Digital.
 *
 *  - program_donasi : program penggalangan dana (terikat ke SumberDana → dimensi ledger).
 *  - donasi         : niat donasi publik (menunggu_transfer) → konfirmasi bendahara → jurnal.
 *  - beasiswa       : potongan SPP per santri ditanggung dana sosial (zakat/infaq/wakaf).
 *
 * Uang DECIMAL(15,2). Status donasi dijaga CHECK constraint. client_ref unik per
 * tenant (NULL tidak saling konflik di PG → efektif partial unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_donasi', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->decimal('target_nominal', 15, 2)->nullable();
            $table->foreignId('sumber_dana_id')->constrained('sumber_dana')->restrictOnDelete();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'aktif']);
        });

        Schema::create('donasi', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('program_donasi_id')->constrained('program_donasi')->restrictOnDelete();
            $table->string('nama_donatur');
            $table->string('kontak', 100)->nullable();
            $table->decimal('nominal', 15, 2);
            $table->text('pesan')->nullable();
            $table->string('bukti_path')->nullable();
            $table->string('status', 30)->default('menunggu_transfer');
            $table->unsignedBigInteger('jurnal_id')->nullable();
            $table->foreignId('dikonfirmasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dikonfirmasi_at')->nullable();
            $table->string('nomor_kuitansi', 100)->nullable();
            $table->unsignedInteger('nomor_kuitansi_urut')->nullable();
            $table->string('client_ref', 64)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'client_ref'], 'donasi_client_ref_unique');
            $table->unique(['tenant_id', 'nomor_kuitansi_urut'], 'donasi_nomor_kuitansi_urut_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'program_donasi_id']);
        });

        DB::statement(
            'ALTER TABLE donasi ADD CONSTRAINT donasi_status_check '
            ."CHECK (status IN ('menunggu_transfer', 'dikonfirmasi', 'ditolak'))"
        );

        Schema::create('beasiswa', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->foreignId('sumber_dana_id')->constrained('sumber_dana')->restrictOnDelete();
            $table->decimal('nominal_per_bulan', 15, 2);
            $table->boolean('aktif')->default(true);
            $table->date('mulai');
            $table->date('selesai')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'santri_id', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beasiswa');
        Schema::dropIfExists('donasi');
        Schema::dropIfExists('program_donasi');
    }
};
