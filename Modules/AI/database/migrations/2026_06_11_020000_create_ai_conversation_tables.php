<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-A050 — ai_conversation + ai_message: TABEL TENANT, percakapan WA inbound
 * (Blueprint §5; adaptasi K6: pilot via WAHA+n8n eksisting, volume kecil).
 *
 * PRIVASI (Aturan Emas): nomor WA TIDAK PERNAH disimpan polos —
 *  - nomor_wa_hash       = sha256(nomor + APP_KEY) untuk pencarian/dedup;
 *  - nomor_wa_terenkripsi = Crypt::encryptString(nomor) HANYA untuk balasan.
 * Isi pesan disimpan SUDAH TERREDAKSI (PiiRedactor::redactPaksa) — teks asli
 * hanya hidup in-memory/terenkripsi di payload job, tak pernah polos di DB/log.
 * Retensi: ai:bersihkan-percakapan (setting tenant retensi_hari, default 90).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversation', function (Blueprint $table) {
            $table->id();
            $table->string('kanal', 16)->default('wa');
            $table->string('nomor_wa_hash', 64);
            $table->text('nomor_wa_terenkripsi');
            // Tanpa FK users/santri: tautan identitas datang di T-A051
            // (verifikasi wali); lifecycle user tak boleh menghapus riwayat.
            $table->unsignedBigInteger('wali_user_id')->nullable();
            $table->unsignedBigInteger('santri_id')->nullable();
            $table->string('status', 16)->default('aktif'); // aktif|handoff|ditutup
            $table->timestamp('terakhir_aktif')->nullable();
            $table->boolean('opt_out')->default(false);
            $table->timestamps();

            $table->unique(['kanal', 'nomor_wa_hash']);
            $table->index('terakhir_aktif');
        });

        Schema::create('ai_message', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('ai_conversation')
                ->cascadeOnDelete();
            $table->string('arah', 8); // masuk|keluar
            $table->text('isi_terredaksi');
            // UNIQUE = idempotensi webhook ganda (WAHA/n8n bisa retry kirim).
            $table->string('wa_message_id')->nullable()->unique();
            $table->jsonb('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_message');
        Schema::dropIfExists('ai_conversation');
    }
};
