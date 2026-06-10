<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-A042 — Ikhtisar materi kitab bersitasi & generator kultum (tabel TENANT).
 *
 * Satu tabel untuk dua jenis dokumen (jenis: ikhtisar|kultum) — pipeline,
 * pagar, dan lifecycle-nya identik (kembaran ai_draf_soal/ai_draf_rpp):
 * antri → menyusun → draf → disahkan (manusia) | gagal.
 *
 * ADAPTASI SADAR vs PRD: "whitelist kitab" GATE-3 belum terisi → sumber v1 =
 * Sumber Pengetahuan tenant (ai_knowledge_source — kitab/materi yang diunggah
 * pesantren sendiri, keputusan owner 2026-06-11: kitab per pesantren). Saat
 * whitelist GATE-3 terisi + korpus shared T-A032 hadir, sumber tinggal
 * bertambah tanpa mengubah skema.
 *
 * isi (jsonb):
 *  - ikhtisar: {poin: [{teks, sitasi_idx[]}], istilah: [{istilah, arti}],
 *    pertanyaan_diskusi: [string]} — sitasi_idx menunjuk indeks kolom
 *    `sitasi` (poin → sumber tertelusur, AC T-A042);
 *  - kultum: {kerangka: [string], pengingat_dalil: [{klaim, rujukan}],
 *    disclaimer} — STRUKTUR pengingat saja, BUKAN konten dalil: klaim
 *    dinormalisasi pipeline berawalan [PERIKSA DALIL], rujukan SELALU
 *    dikosongkan untuk diisi ustadz (Aturan Emas: JANGAN mengarang teks
 *    ayat/hadits/dalil).
 *
 * sitasi (jsonb) = daftar sumber DETERMINISTIK dari pipeline retrieval
 * (judul/bab/halaman/label) — model tidak pernah menulis sitasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_draf_ikhtisar', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 16); // ikhtisar|kultum
            $table->string('judul');
            // restrictOnDelete (spesifikasi T-A042): sumber yang sudah jadi
            // rujukan ikhtisar tidak boleh hilang diam-diam — sitasi dokumen
            // harus tetap tertelusur; nonaktifkan sumber bila tak dipakai.
            // nullable: draf kultum boleh tanpa sumber spesifik (seluruh
            // korpus tenant).
            $table->foreignId('source_id')
                ->nullable()
                ->constrained('ai_knowledge_source')
                ->restrictOnDelete();
            $table->string('bab', 150)->nullable(); // ikhtisar: bab/bagian kitab
            $table->string('tema', 150)->nullable(); // kultum: tema kerangka
            $table->unsignedSmallInteger('durasi_menit')->nullable(); // kultum
            $table->string('status', 16)->default('antri'); // antri|menyusun|draf|disahkan|gagal
            $table->jsonb('isi')->nullable();
            $table->jsonb('sitasi')->nullable();
            $table->string('error_ringkas', 500)->nullable();
            $table->unsignedBigInteger('dibuat_oleh')->nullable();
            $table->unsignedBigInteger('disahkan_oleh')->nullable();
            $table->timestamp('disahkan_pada')->nullable();
            // Kunci idempotensi draf; kunci metering diturunkan deterministik
            // ("{key}:g1", retry parse ":g1:r1") — retry job tidak
            // menggandakan kredit (dedup ledger T-A002).
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index('status');
            $table->index('jenis');
            $table->index('dibuat_oleh');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_draf_ikhtisar');
    }
};
