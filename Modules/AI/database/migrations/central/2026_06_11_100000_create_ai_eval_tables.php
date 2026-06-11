<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-A024 — Eval harness syariah & faktual + gate rilis (Blueprint K7 + §10).
 *
 * Tabel CENTRAL (QA level platform, Blueprint §5):
 *  - ai_eval_case: bank kasus eval per suite. Kasus syariah HANYA lahir dari
 *    impor berkas kurasi ustadz / kasus perilaku PRD (Aturan Emas #8 —
 *    TIDAK PERNAH dikarang pengembang/AI); kasus teknis (injection, mirror
 *    policy linguistik, routing intent) diimpor seeder dari berkas JSON
 *    eksisting Modules/AI/database/seeders/data.
 *  - ai_eval_run: laporan tiap eksekusi `ai:eval` — gate rilis dibaca dari
 *    sini (lulus_gate), BUKAN dari CI (K7: test.yml selalu merah karena
 *    DB-per-tenant). Run juga merekam model + versi prompt aktif: ganti
 *    model/prompt = wajib eval ulang (bandingkan kolom antar run).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_eval_case', function (Blueprint $table): void {
            $table->id();
            // syariah | faktual | injection (lihat AiEvalCase::SUITES).
            $table->string('suite', 16);
            // Kode unik kasus (inj-001, pol-001, int-001, tool-001, EV-001).
            $table->string('kode', 64)->unique();
            // Kanal asal kasus (web/wa/internal/wa_wali/santri/asatidz_level2)
            // — dipetakan EvalRunner ke channel AiGenerateRequest.
            $table->string('kanal', 32)->default('web');
            // {pertanyaan, context?, setup?} — setup = kebutuhan konteks tenant
            // uji (level_fatwa, data santri/tagihan minimal, dst).
            $table->jsonb('input');
            // {jenis: dialihkan|dijawab_dari_rujukan|ditolak_tanpa_rujukan|
            //  diblokir_policy|lolos|route_intent, detail?}.
            $table->jsonb('harapan');
            // kurasi_ustadz | teknis | prd_verbatim — label asal kasus WAJIB
            // jelas (kasus syariah tanpa label kurasi tidak boleh diklaim
            // sebagai hasil tinjauan ustadz).
            $table->string('sumber', 24);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['suite', 'aktif']);
        });

        Schema::create('ai_eval_run', function (Blueprint $table): void {
            $table->id();
            $table->string('suite', 16);
            $table->unsignedInteger('total');
            $table->unsignedInteger('lulus');
            $table->unsignedInteger('gagal');
            // Skor 0..1 (presisi 4 desimal — ambang 0.98/0.97/1.0).
            $table->decimal('skor', 5, 4);
            $table->decimal('ambang', 5, 4);
            $table->boolean('lulus_gate');
            // Identitas model produksi yang dievaluasi ("provider/model").
            $table->string('model', 128);
            // Versi prompt aktif per agen saat run: {agent: {versi_id, source}}.
            $table->jsonb('prompt_versi')->nullable();
            // {gagal: [{kode, alasan}...], dilewati: [{kode, alasan}...]}.
            $table->jsonb('detail_gagal')->nullable();
            $table->unsignedInteger('durasi_ms')->default(0);
            // Tenant konteks eval (tenant khusus eval / --tenant=...).
            $table->string('tenant_id')->nullable();
            $table->string('dijalankan_oleh')->nullable();
            // Run tidak pernah dimutasi — hanya ditambah (tanpa updated_at).
            $table->timestamp('created_at')->useCurrent();

            $table->index(['suite', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_eval_run');
        Schema::dropIfExists('ai_eval_case');
    }
};
