<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-A041 — Generator RPP / Modul Ajar (tabel TENANT).
 *
 * ai_template_rpp — struktur dokumen RPP MILIK TENANT sepenuhnya
 * (Wakakurikulum membuat/mengubah). Aturan Emas #11 + #8: taksonomi regulasi
 * (KMA 939/2024, KMA 1495/2025, dst.) TIDAK di-hardcode — itu konten yang
 * harus ditinjau manusia; platform hanya menyediakan SATU template default
 * generik netral (field umum RPP) yang bisa diubah penuh oleh tenant.
 *
 * ai_draf_rpp — draf dokumen hasil pengisian AI per seksi. Human-in-the-loop
 * (pagar #3): seluruh baris lahir berstatus draf-pipeline (antri → menyusun →
 * draf) dan HANYA berpindah ke `disahkan` lewat aksi manusia pemegang
 * permission (RppGenerator::sahkan — identitas + waktu tercatat). Isi (jsonb)
 * memuat sitasi DETERMINISTIK per seksi dari pipeline retrieval — bukan dari
 * model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_template_rpp', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            // Teks bebas (MTs/MA/Ma'had Aly/Diniyah/...) — BUKAN enum:
            // jenis lembaga adalah istilah milik tenant, bukan milik platform.
            $table->string('jenis_lembaga', 100)->nullable();
            // Array seksi: {kunci, judul, petunjuk, wajib}.
            // petunjuk = instruksi pengisian untuk AI/guru; petunjuk yang
            // memuat kata "salin" menandai seksi DISALIN dari isian guru
            // tanpa LLM (lihat RppGenerator).
            $table->jsonb('seksi');
            $table->boolean('aktif')->default(true);
            $table->unsignedBigInteger('dibuat_oleh')->nullable(); // tanpa FK: lifecycle users tak menggugurkan template
            $table->timestamps();

            $table->index('aktif');
        });

        Schema::create('ai_draf_rpp', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: template yang sudah dipakai draf tidak boleh
            // hilang diam-diam — UI menolak hapus dengan pesan santun
            // (matikan lewat kolom `aktif` bila tidak dipakai lagi).
            $table->foreignId('template_id')
                ->constrained('ai_template_rpp')
                ->restrictOnDelete();
            $table->string('judul');
            $table->string('mapel', 150); // teks bebas: nama mapel/kitab
            $table->string('kelas', 100)->nullable(); // kelas/jenjang, teks bebas
            // Sumber pengetahuan spesifik (opsional). nullOnDelete: sumber
            // dihapus tuntas (right to erasure) tidak menggugurkan draf —
            // sitasi historis tetap tersimpan di isi (jsonb).
            $table->foreignId('source_id')
                ->nullable()
                ->constrained('ai_knowledge_source')
                ->nullOnDelete();
            // Isian awal guru per seksi: map {kunci seksi: teks} — opsional.
            $table->jsonb('input_guru')->nullable();
            $table->string('status', 16)->default('antri'); // antri|menyusun|draf|disahkan|gagal
            // Array isi per seksi: {kunci, judul, konten, sitasi[]}.
            // sitasi per seksi = label deterministik pipeline (judul/bab/halaman).
            $table->jsonb('isi')->nullable();
            $table->string('error_ringkas', 500)->nullable();
            $table->unsignedBigInteger('dibuat_oleh')->nullable();
            $table->unsignedBigInteger('disahkan_oleh')->nullable();
            $table->timestamp('disahkan_pada')->nullable();
            // Kunci idempotensi draf; kunci metering per seksi diturunkan
            // deterministik darinya ("{key}:s{kunci-seksi}") — retry job
            // tidak menggandakan kredit (dedup ledger T-A002).
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index('status');
            $table->index('dibuat_oleh');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_draf_rpp');
        Schema::dropIfExists('ai_template_rpp');
    }
};
