<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F3-PWA — Pesan guru → wali santri (apresiasi/perhatian/info).
 *
 * Jenis 'perhatian' bisa tertahan moderasi (Setting
 * kesantrian.moderasi_pesan_perhatian, default ON) sebelum terkirim ke wali.
 * Idempoten via client_ref: partial unique NULL-safe (tenant, client_ref) —
 * dedup di service WAJIB scoped guru_id+santri_id (ref orang lain → 409).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pesan_wali', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('guru_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->string('jenis', 20); // apresiasi|perhatian|info (CHECK di bawah)
            $table->text('isi');
            $table->string('status', 30)->default('draft'); // draft|menunggu_moderasi|terkirim|ditolak
            $table->string('dikirim_via', 20)->nullable(); // portal|wa|keduanya
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('alasan_tolak')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('client_ref', 191)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'santri_id'], 'pesan_wali_tenant_santri_index');
            $table->index(['tenant_id', 'status'], 'pesan_wali_tenant_status_index');
        });

        DB::statement("ALTER TABLE pesan_wali ADD CONSTRAINT pesan_wali_jenis_check CHECK (jenis IN ('apresiasi', 'perhatian', 'info'))");
        DB::statement("ALTER TABLE pesan_wali ADD CONSTRAINT pesan_wali_status_check CHECK (status IN ('draft', 'menunggu_moderasi', 'terkirim', 'ditolak'))");
        DB::statement("ALTER TABLE pesan_wali ADD CONSTRAINT pesan_wali_dikirim_via_check CHECK (dikirim_via IS NULL OR dikirim_via IN ('portal', 'wa', 'keduanya'))");
        // Partial unique NULL-safe — gerbang dedup race-safe di tingkat DB.
        DB::statement('CREATE UNIQUE INDEX pesan_wali_client_ref_unique ON pesan_wali(tenant_id, client_ref) WHERE client_ref IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('pesan_wali');
    }
};
