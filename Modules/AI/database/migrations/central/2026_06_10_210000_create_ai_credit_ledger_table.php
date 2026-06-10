<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-A002 — ai_credit_ledger: ledger kredit AI per tenant, APPEND-ONLY,
 * tabel CENTRAL (Blueprint §5: billing = urusan platform, sejajar
 * tenant_subscriptions). Path central/ TIDAK ikut tenants:migrate.
 *
 * idempotency_key NOT NULL UNIQUE — pelajaran PR#49: kunci NULL tidak
 * ter-dedup oleh unique constraint SQL (NULL != NULL), maka operasi tulis
 * bernilai uang WAJIB selalu membawa kunci.
 *
 * Append-only ditegakkan di DB via trigger PostgreSQL (pola persis
 * ai_audit_log): BEFORE UPDATE OR DELETE → RAISE EXCEPTION.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_credit_ledger', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            // alokasi | topup | pemakaian | penyesuaian
            $table->string('jenis', 20);
            // Positif = kredit masuk (alokasi/topup), negatif = keluar (pemakaian).
            $table->decimal('jumlah_kredit', 14, 4);
            $table->decimal('saldo_setelah', 14, 4);
            $table->string('referensi')->nullable();
            $table->string('idempotency_key')->unique(); // NOT NULL (pelajaran PR#49)
            $table->string('keterangan', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            // TANPA updated_at — append-only.

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'jenis', 'created_at']);
        });

        if (DB::connection($this->getConnection())->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION ai_credit_ledger_append_only() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'ai_credit_ledger bersifat append-only: % tidak diizinkan.', TG_OP;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER trg_ai_credit_ledger_append_only
                    BEFORE UPDATE OR DELETE ON ai_credit_ledger
                    FOR EACH ROW EXECUTE FUNCTION ai_credit_ledger_append_only();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection($this->getConnection())->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_ai_credit_ledger_append_only ON ai_credit_ledger');
            DB::unprepared('DROP FUNCTION IF EXISTS ai_credit_ledger_append_only()');
        }

        Schema::dropIfExists('ai_credit_ledger');
    }
};
