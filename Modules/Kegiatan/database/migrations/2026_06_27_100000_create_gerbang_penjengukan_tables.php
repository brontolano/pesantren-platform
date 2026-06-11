<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-29 Pos Gerbang + WP-30 Penjengukan.
 * log_gerbang: catatan keluar/masuk santri di pos keamanan.
 * pengajuan_jenguk: slot jenguk diajukan wali via PWA.
 * kunjungan_wali: ditambah pengajuan_id + slot (reuse tabel kunjungan F-Kegiatan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_gerbang', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            // Jejak audit: hapus santri TIDAK boleh menghapus riwayat gerbang.
            $table->foreignId('santri_id')->nullable()->constrained('santri')->nullOnDelete();
            $table->string('arah'); // keluar | masuk (CHECK di bawah)
            $table->timestamp('waktu');
            $table->foreignId('petugas_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('izin_id')->nullable()->constrained('izin_santri')->nullOnDelete();
            $table->boolean('is_tanpa_izin')->default(false);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'santri_id', 'waktu']);
            $table->index(['tenant_id', 'waktu']);
        });

        DB::statement("ALTER TABLE log_gerbang ADD CONSTRAINT log_gerbang_arah_check CHECK (arah IN ('keluar', 'masuk'))");

        Schema::create('pengajuan_jenguk', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->foreignId('pengaju_id')->constrained('users')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('status')->default('diajukan'); // CHECK di bawah
            $table->string('client_ref')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'tanggal', 'status']);
            $table->index(['tenant_id', 'santri_id']);
        });

        DB::statement("ALTER TABLE pengajuan_jenguk ADD CONSTRAINT pengajuan_jenguk_status_check CHECK (status IN ('diajukan', 'dikonfirmasi', 'ditolak', 'hadir', 'batal'))");
        // Idempoten: satu client_ref = satu pengajuan per tenant. Scoping
        // pengaju+santri dijaga di service (ref orang lain → 409).
        DB::statement('CREATE UNIQUE INDEX unique_jenguk_client_ref_per_tenant ON pengajuan_jenguk(tenant_id, client_ref) WHERE client_ref IS NOT NULL');
        // Satu pengajuan hidup per (santri, tanggal) — backstop service-check.
        DB::statement("CREATE UNIQUE INDEX unique_jenguk_santri_tanggal_hidup ON pengajuan_jenguk(tenant_id, santri_id, tanggal) WHERE status IN ('diajukan', 'dikonfirmasi', 'hadir')");

        Schema::table('kunjungan_wali', function (Blueprint $table): void {
            $table->foreignId('pengajuan_id')->nullable()->constrained('pengajuan_jenguk')->nullOnDelete();
            $table->string('slot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kunjungan_wali', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pengajuan_id');
            $table->dropColumn('slot');
        });

        Schema::dropIfExists('pengajuan_jenguk');
        Schema::dropIfExists('log_gerbang');
    }
};
