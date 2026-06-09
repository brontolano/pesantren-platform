<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul POS Kas (loket Kantor Keuangan/Perijinan) — tenant-scoped.
 *
 *  - pos_kategori : kategori transaksi configurable per tenant (akun lawan Kas).
 *  - pos_sesi     : sesi/shift kasir (buka/tutup + rekonsiliasi selisih).
 *  - pos_transaksi: tiap pemasukan/pengeluaran tunai (link ke jurnal Ledger).
 *
 * Referensi lintas-modul (akun/sumber_dana/santri/jurnal) sengaja TANPA FK
 * constraint agar tahan urutan migrasi antar-modul; cukup di-index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_kategori', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('kode', 40)->nullable();
            $table->string('nama');
            $table->string('jenis', 12);                 // pemasukan | pengeluaran
            $table->string('handler', 24)->default('umum'); // umum|tabungan_setor|tabungan_tarik|daftar_ulang|spp|pengeluaran|koperasi|infaq
            $table->unsignedBigInteger('akun_id')->nullable();        // akun lawan Kas (Ledger COA)
            $table->unsignedBigInteger('sumber_dana_id')->nullable(); // dimensi sumber dana (Ledger)
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'kode']);
            $table->index(['tenant_id', 'aktif']);
        });

        Schema::create('pos_sesi', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('kasir_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pos_jenis', 40)->default('kantor_keuangan'); // label loket
            $table->decimal('saldo_awal', 15, 2)->default(0);
            $table->decimal('saldo_sistem', 15, 2)->default(0);  // saldo_awal + masuk - keluar
            $table->decimal('saldo_fisik', 15, 2)->nullable();   // hitung uang saat tutup
            $table->decimal('selisih', 15, 2)->nullable();       // fisik - sistem
            $table->string('status', 10)->default('buka');       // buka | tutup
            $table->timestamp('dibuka_pada')->nullable();
            $table->timestamp('ditutup_pada')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'kasir_id']);
        });

        Schema::create('pos_transaksi', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('sesi_id')->constrained('pos_sesi')->cascadeOnDelete();
            $table->foreignId('kategori_id')->nullable()->constrained('pos_kategori')->nullOnDelete();
            $table->string('jenis', 12);                 // pemasukan | pengeluaran
            $table->decimal('nominal', 15, 2);
            $table->unsignedBigInteger('santri_id')->nullable();
            $table->string('ref_type')->nullable();
            $table->string('ref_id')->nullable();
            $table->unsignedBigInteger('jurnal_id')->nullable(); // link jurnal Ledger (direct posting)
            $table->string('keterangan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'sesi_id']);
            $table->index(['tenant_id', 'kategori_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_transaksi');
        Schema::dropIfExists('pos_sesi');
        Schema::dropIfExists('pos_kategori');
    }
};
