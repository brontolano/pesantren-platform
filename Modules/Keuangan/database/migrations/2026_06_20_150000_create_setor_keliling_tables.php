<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setor keliling: petugas mencatat setoran tabungan santri di lapangan sebagai
 * DRAF (belum menyentuh saldo), lalu bendahara menyetujui → posting ke saldo
 * via TabunganService (jalur uang teruji). Additif & idempoten.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('setor_keliling_sesi')) {
            Schema::create('setor_keliling_sesi', function (Blueprint $table): void {
                $table->id();
                $table->string('tenant_id')->index();
                $table->foreignId('petugas_id')->constrained('users');
                $table->date('tanggal');
                $table->string('lokasi')->nullable();
                // draf → diajukan → disetujui | ditolak
                $table->string('status')->default('draf')->index();
                $table->decimal('total', 14, 2)->default(0);
                $table->foreignId('approved_by')->nullable()->constrained('users');
                $table->timestamp('approved_at')->nullable();
                $table->text('alasan_tolak')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('setor_keliling_item')) {
            Schema::create('setor_keliling_item', function (Blueprint $table): void {
                $table->id();
                $table->string('tenant_id')->index();
                $table->foreignId('sesi_id')->constrained('setor_keliling_sesi')->cascadeOnDelete();
                $table->foreignId('santri_id')->constrained('santri');
                $table->decimal('nominal', 14, 2);
                $table->string('catatan')->nullable();
                // Diisi id TabunganTransaksi saat approve — penanda item SUDAH
                // diposting (backstop idempotensi tingkat item).
                $table->foreignId('tabungan_transaksi_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('setor_keliling_item');
        Schema::dropIfExists('setor_keliling_sesi');
    }
};
