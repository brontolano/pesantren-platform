<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-A040 — ai_draf_soal: bank soal DRAF hasil Generator Soal & Kisi-kisi
 * (tabel TENANT — paket draf milik asatidz per pesantren).
 *
 * Human-in-the-loop (pagar #3): seluruh baris lahir berstatus draf-pipeline
 * (antri → menyusun → draf) dan HANYA berpindah ke `disahkan` lewat aksi
 * manusia pemegang permission (SoalGenerator::sahkan — dicatat identitas +
 * waktu). Butir (jsonb) memuat sitasi DETERMINISTIK per butir dari pipeline
 * retrieval — bukan dari model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_draf_soal', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->string('mapel'); // teks bebas: nama mapel/kitab
            // Sumber pengetahuan spesifik (opsional). nullOnDelete: sumber
            // dihapus tuntas (right to erasure) tidak menggugurkan draf —
            // sitasi historis tetap tersimpan di butir (jsonb).
            $table->foreignId('source_id')
                ->nullable()
                ->constrained('ai_knowledge_source')
                ->nullOnDelete();
            $table->string('bab')->nullable();
            $table->string('jenjang', 64)->nullable();
            $table->unsignedSmallInteger('jumlah');
            $table->string('tipe', 16)->default('pilihan_ganda'); // pilihan_ganda|esai|campuran
            $table->string('tingkat_kognitif', 2)->nullable(); // C1..C6 (taksonomi sederhana)
            $table->string('status', 16)->default('antri'); // antri|menyusun|draf|disahkan|gagal
            // Array butir: {no, pertanyaan, opsi[]|null, kunci, kisi, sitasi[]}.
            // sitasi per butir = label deterministik pipeline (judul/bab/halaman).
            $table->jsonb('butir')->nullable();
            $table->string('error_ringkas', 500)->nullable();
            $table->unsignedBigInteger('dibuat_oleh')->nullable(); // tanpa FK: lifecycle users tak menggugurkan draf
            $table->unsignedBigInteger('disahkan_oleh')->nullable();
            $table->timestamp('disahkan_pada')->nullable();
            // Kunci idempotensi paket; kunci metering per batch diturunkan
            // deterministik darinya ("{key}:b{n}") — retry job tidak
            // menggandakan kredit (dedup ledger T-A002).
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index('status');
            $table->index('dibuat_oleh');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_draf_soal');
    }
};
