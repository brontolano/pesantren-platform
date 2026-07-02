<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-A031 — kolom FTS generated `konten_fts` di ai_chunk (tabel TENANT) untuk
 * jalur keyword pada retrieval hybrid (Blueprint §4.2 langkah 3).
 *
 * Config 'simple': PostgreSQL default tidak punya konfigurasi bahasa
 * Indonesia — 'simple' (tanpa stemming/stopword) cukup untuk pencocokan
 * keyword; ranking semantik ditangani jalur vektor.
 *
 * Guard pgsql + idempoten (hasColumn / IF NOT EXISTS) — aman dijalankan
 * ulang di tenant lama (tenant:reprovision) maupun tenant baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection($this->getConnection())->getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasColumn('ai_chunk', 'konten_fts')) {
            DB::statement(
                'ALTER TABLE ai_chunk ADD COLUMN konten_fts tsvector '
                ."GENERATED ALWAYS AS (to_tsvector('simple', coalesce(konten, ''))) STORED"
            );
        }

        DB::statement(
            'CREATE INDEX IF NOT EXISTS ai_chunk_konten_fts_gin ON ai_chunk USING gin (konten_fts)'
        );
    }

    public function down(): void
    {
        if (DB::connection($this->getConnection())->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS ai_chunk_konten_fts_gin');
        DB::statement('ALTER TABLE ai_chunk DROP COLUMN IF EXISTS konten_fts');
    }
};
