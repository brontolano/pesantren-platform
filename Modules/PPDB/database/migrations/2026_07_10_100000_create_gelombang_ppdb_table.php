<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * gap-130 — Master Gelombang PPDB terstruktur.
 *
 * Selama ini "gelombang" hanya kolom teks-bebas `gelombang` di tabel
 * `calon_santri`. Tabel ini menjadi MASTER referensi gelombang (additif).
 * TIDAK mengubah/menghapus kolom `calon_santri.gelombang` dan TIDAK di-wire
 * paksa ke alur registrasi publik — wiring adalah langkah owner terpisah.
 *
 * Pola tenant: mirror `calon_santri` (kolom `tenant_id` string + FK ke tenants
 * + index per tenant). Dijalankan via `tenants:migrate`; idempoten-aman dengan
 * guard Schema::hasTable.
 *
 * NON-UANG: `biaya_pendaftaran` hanya nominal referensi konfigurasi gelombang,
 * BUKAN posting jurnal / tagihan / ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gelombang_ppdb')) {
            return;
        }

        Schema::create('gelombang_ppdb', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama');
            $table->string('tahun_ajaran');
            $table->date('tanggal_buka')->nullable();
            $table->date('tanggal_tutup')->nullable();
            $table->unsignedInteger('kuota')->nullable();
            // Nominal referensi konfigurasi gelombang saja (NON-UANG; tidak
            // pernah diposting ke jurnal/tagihan). Disimpan dalam rupiah penuh.
            $table->unsignedBigInteger('biaya_pendaftaran')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'aktif']);
            $table->index(['tenant_id', 'tahun_ajaran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gelombang_ppdb');
    }
};
