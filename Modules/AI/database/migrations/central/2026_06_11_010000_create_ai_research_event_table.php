<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-A013 — ai_research_event: telemetri riset produk BER-OPT-IN, tabel
 * CENTRAL (dashboard "Suara Pasar" milik platform, lintas tenant).
 *
 * Pagar privasi (PRD §6.4 + ADR-6):
 *  - Baris HANYA lahir bila optin_riset = true pada setting AKTIF tenant
 *    (ResearchTelemetry::aktif() — no-op senyap bila tidak opt-in).
 *  - payload HANYA memuat agregat/kategori/kunci konfigurasi — TIDAK PERNAH
 *    isi percakapan, teks pertanyaan, PII, atau identitas santri/wali.
 *  - SENGAJA TANPA trigger append-only (beda dengan ai_credit_ledger /
 *    ai_audit_log): pencabutan izin riset WAJIB bisa MENGHAPUS seluruh
 *    baris tenant (ResearchTelemetry::cabutIzin) — hak cabut PRD §6.4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_research_event', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            // snapshot_konfigurasi | eskalasi_kategori | rating_jawaban
            $table->string('jenis', 32);
            // HANYA agregat/kategori — skema per jenis didokumentasikan di
            // model AiResearchEvent dan Modules/AI/README.md.
            $table->jsonb('payload');
            // Bulan periode (selalu tanggal 1) — sumbu dashboard bulanan.
            $table->date('periode');
            $table->timestamp('created_at')->useCurrent();
            // TANPA updated_at — event tidak pernah dimutasi, hanya
            // ditambah (saat opt-in) atau dihapus (saat cabut izin).

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['jenis', 'periode']);
            // Penghapusan massal saat cabut izin + agregasi per tenant.
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_research_event');
    }
};
