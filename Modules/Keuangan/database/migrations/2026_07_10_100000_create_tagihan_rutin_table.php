<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tagihan Rutin — "langganan" tagihan yang diterbitkan otomatis tiap periode
 * (mis. SPP bulanan, uang makan, asrama). Satu baris = satu ATURAN; tagihan
 * nyata di-generate per santri oleh command keuangan:terbitkan-tagihan-rutin.
 *
 * Fleksibel: target bisa SEMUA santri / per pondok (lembaga) / per kelas / per
 * tingkat, nominal per-aturan (buat beberapa aturan utk nominal berbeda).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan_rutin', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama');                       // "SPP Bulanan Banin"
            $table->string('jenis_tagihan', 100);         // spp, makan, asrama, dll (bebas)
            $table->string('frekuensi', 20)->default('bulanan'); // bulanan | semester | tahunan
            $table->decimal('nominal', 15, 2);

            // Target santri
            $table->string('target_tipe', 20)->default('semua'); // semua|lembaga|kelas|tingkat
            $table->foreignId('target_lembaga_id')->nullable();
            $table->foreignId('target_kelas_id')->nullable();
            $table->string('target_tingkat', 50)->nullable();

            // Jadwal
            $table->unsignedTinyInteger('tanggal_terbit')->default(1);  // hari ke-N tiap periode (1-28)
            $table->unsignedSmallInteger('tempo_hari')->default(10);    // jatuh tempo = terbit + N hari

            // Dimensi keuangan (selaras Tagihan)
            $table->foreignId('sumber_dana_id')->nullable();
            $table->foreignId('divisi_unit_id')->nullable();
            $table->string('keterangan')->nullable();

            $table->boolean('notif_wa')->default(false);  // kirim WA ke wali saat terbit
            $table->boolean('aktif')->default(true);
            $table->string('terakhir_terbit_periode', 7)->nullable(); // '2026-07' guard idempotensi aturan

            $table->timestamps();

            $table->index(['tenant_id', 'aktif']);
            $table->index(['tenant_id', 'tanggal_terbit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan_rutin');
    }
};
