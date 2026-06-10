<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-A010 — ai_tenant_setting (versioned) + ai_approval: TABEL TENANT
 * (Blueprint §5). Perwujudan Aturan Emas #11: seluruh kebijakan perilaku AI
 * per pesantren hidup di DATA (payload jsonb), bukan di kode.
 *
 * - ai_tenant_setting: tiap perubahan = baris VERSI baru (audit siapa/kapan).
 *   Maksimal SATU baris berstatus 'aktif' — ditegakkan partial unique index
 *   PostgreSQL (driver lain dilindungi konvensi TenantSettingService).
 * - ai_approval: jejak persetujuan kyai/pimpinan per versi, menyimpan hash
 *   ringkasan kebijakan yang dibaca & disetujui (PRD §6.2 Langkah 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tenant_setting', function (Blueprint $table) {
            $table->id();
            // Nomor versi naik monoton per tenant (DB-per-tenant → unik global
            // di DB ini). Dihitung TenantSettingService dalam transaksi.
            $table->unsignedInteger('versi')->unique();
            // Seluruh field Wizard PRD §6.3 — kebijakan di data (Aturan Emas #11).
            $table->jsonb('payload');
            $table->string('status', 24)->default('draft'); // draft|pending_approval|aktif|arsip
            // Tanpa FK users: lifecycle user tidak boleh menghapus jejak versi.
            $table->unsignedBigInteger('dibuat_oleh')->nullable();
            $table->text('ringkasan_perubahan')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('ai_approval', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_tenant_setting_id')
                ->constrained('ai_tenant_setting')
                ->cascadeOnDelete();
            // Hash ringkasan kebijakan yang DIBACA kyai saat memutuskan —
            // bukti bahwa yang disetujui = yang tersimpan (anti-tukar diam2).
            $table->string('ringkasan_hash', 64);
            $table->string('status', 16)->default('menunggu'); // menunggu|disetujui|ditolak
            $table->unsignedBigInteger('disetujui_oleh')->nullable();
            $table->text('alasan')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // Maksimal SATU versi aktif — partial unique index (hanya PostgreSQL).
        if (DB::connection($this->getConnection())->getDriverName() === 'pgsql') {
            DB::statement(
                "CREATE UNIQUE INDEX ai_tenant_setting_satu_aktif
                 ON ai_tenant_setting (status) WHERE status = 'aktif'"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_approval');
        Schema::dropIfExists('ai_tenant_setting');
    }
};
