<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-A030 — ai_knowledge_source + ai_chunk: penyimpanan RAG per TENANT
 * (Blueprint §5: pengetahuan & vektor hidup di DB tenant, pgvector).
 *
 * Toleransi lingkungan (Blueprint §5 + catatan test lokal):
 *  - kolom `embedding vector(1024)` (bge-m3) HANYA dibuat bila driver pgsql
 *    DAN extension pgvector tersedia (CREATE EXTENSION berhasil);
 *  - `embedding_json` (jsonb) SELALU ada sebagai penyimpanan fallback —
 *    lingkungan tanpa pgvector (mis. PG lokal dev/test) tetap bisa ingest
 *    penuh; backfill kolom vector dilakukan saat re-ingest di produksi.
 *  - index ANN: coba HNSW, fallback IVFFlat, fallback tanpa index — retrieval
 *    (T-A031) tetap benar tanpa index, hanya lebih lambat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_knowledge_source', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->string('kategori', 32)->default('lainnya'); // kurikulum|sop|ppdb|faq|lainnya
            $table->string('path_file')->nullable(); // file di disk tenant (pola /_tenant-storage)
            $table->string('mime', 128)->nullable();
            $table->string('status', 16)->default('diunggah'); // diunggah|memproses|siap|gagal
            $table->string('error_ringkas', 500)->nullable();
            $table->unsignedInteger('jumlah_chunk')->default(0);
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('versi_ingest')->default(1);
            $table->unsignedBigInteger('diunggah_oleh')->nullable(); // tanpa FK: lifecycle users tak boleh menggugurkan sumber
            $table->timestamps();

            $table->index(['kategori', 'aktif']);
            $table->index('status');
        });

        Schema::create('ai_chunk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')
                ->constrained('ai_knowledge_source')
                ->cascadeOnDelete(); // right-to-erasure (ADR-6): hapus sumber = hapus semua chunk
            $table->unsignedInteger('urutan');
            $table->text('konten'); // SUDAH melewati ContentSanitizer::sanitize
            $table->jsonb('metadata')->nullable(); // {judul, bab?, halaman?}
            $table->jsonb('embedding_json')->nullable(); // fallback penyimpanan vektor (selalu terisi)
            $table->timestamps();

            $table->unique(['source_id', 'urutan']);
        });

        if ($this->pgvectorTersedia()) {
            // bge-m3 = 1024 dimensi (config ai.rag.embedding_dim).
            DB::statement('ALTER TABLE ai_chunk ADD COLUMN embedding vector(1024)');
            $this->buatIndexAnn();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chunk');
        Schema::dropIfExists('ai_knowledge_source');
    }

    /**
     * pgsql + extension vector tersedia → kolom vector dibuat.
     *
     * PENTING: ketersediaan dicek via pg_available_extensions (SELECT murni),
     * BUKAN mencoba CREATE EXTENSION lalu menangkap exception — statement
     * gagal akan MERACUNI transaksi migrasi PostgreSQL (semua statement
     * berikutnya ikut gagal, Schema::create ter-rollback).
     */
    private function pgvectorTersedia(): bool
    {
        if (DB::connection($this->getConnection())->getDriverName() !== 'pgsql') {
            return false;
        }

        try {
            $ada = DB::selectOne(
                "SELECT 1 AS ok FROM pg_available_extensions WHERE name = 'vector'",
            ) !== null;

            if ($ada) {
                DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            }

            return $ada;
        } catch (\Throwable) {
            // Lingkungan tanpa pgvector — embedding_json tetap menampung.
            return false;
        }
    }

    /**
     * Index ANN: HNSW bila pgvector >= 0.5.0, selain itu IVFFlat. Versi dicek
     * via katalog (bukan try/catch CREATE INDEX — statement gagal meracuni
     * transaksi migrasi PostgreSQL). Tanpa index pun retrieval tetap benar
     * (seq scan), hanya lebih lambat.
     */
    private function buatIndexAnn(): void
    {
        $versi = (string) (DB::selectOne(
            "SELECT extversion AS v FROM pg_extension WHERE extname = 'vector'",
        )->v ?? '0');

        if (version_compare($versi, '0.5.0', '>=')) {
            DB::statement(
                'CREATE INDEX ai_chunk_embedding_hnsw ON ai_chunk USING hnsw (embedding vector_cosine_ops)'
            );
        } else {
            DB::statement(
                'CREATE INDEX ai_chunk_embedding_ivfflat ON ai_chunk USING ivfflat (embedding vector_cosine_ops)'
            );
        }
    }
};
