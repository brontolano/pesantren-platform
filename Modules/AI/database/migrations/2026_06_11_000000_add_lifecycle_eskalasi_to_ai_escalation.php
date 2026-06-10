<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-A023 — kolom siklus hidup antrean "Tanya Ustadz" pada ai_escalation
 * (tabel TENANT, dasar dibuat T-A020):
 *
 *  - sla_deadline    : tenggat jawab sejak ditugaskan (setting eskalasi_tujuan
 *                      .sla_jam / config ai.eskalasi.sla_jam_default);
 *  - diingatkan_pada : penanda pengingat SLA SUDAH terkirim (sekali saja) —
 *                      di-reset saat penugasan ulang (tenggat baru);
 *  - dijawab_oleh/dijawab_pada : identitas & waktu jawaban manusia
 *                      (tanpa FK users — lifecycle user tidak boleh menghapus
 *                      jejak antrean, sama dengan ditugaskan_ke);
 *  - ditutup_pada    : waktu eskalasi ditutup.
 *
 * Index (status, sla_deadline) melayani pemindaian pengingat SLA terjadwal;
 * filter status murni sudah terlayani index (status, created_at) dari T-A020.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_escalation', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_escalation', 'sla_deadline')) {
                $table->timestamp('sla_deadline')->nullable();
            }
            if (! Schema::hasColumn('ai_escalation', 'diingatkan_pada')) {
                $table->timestamp('diingatkan_pada')->nullable();
            }
            if (! Schema::hasColumn('ai_escalation', 'dijawab_oleh')) {
                $table->unsignedBigInteger('dijawab_oleh')->nullable();
            }
            if (! Schema::hasColumn('ai_escalation', 'dijawab_pada')) {
                $table->timestamp('dijawab_pada')->nullable();
            }
            if (! Schema::hasColumn('ai_escalation', 'ditutup_pada')) {
                $table->timestamp('ditutup_pada')->nullable();
            }
        });

        Schema::table('ai_escalation', function (Blueprint $table) {
            $table->index(['status', 'sla_deadline'], 'ai_escalation_status_sla_deadline_index');
        });
    }

    public function down(): void
    {
        Schema::table('ai_escalation', function (Blueprint $table) {
            $table->dropIndex('ai_escalation_status_sla_deadline_index');
            $table->dropColumn([
                'sla_deadline',
                'diingatkan_pada',
                'dijawab_oleh',
                'dijawab_pada',
                'ditutup_pada',
            ]);
        });
    }
};
