<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-08 — Kelulusan & Mutasi.
 *
 * alumni:
 * - tanggal_lulus           : tanggal SK kelulusan (status_santri = jejak per santri).
 * - lembaga_id              : lembaga asal saat lulus (template & nomor syahadah per lembaga).
 * - nomor_syahadah(+urut)   : sertifikat internal; urut = counter race-safe per
 *                             (tenant, tahun_lulus) — partial unique jadi backstop balapan
 *                             (pola SuratService).
 * - nomor_ijazah/jenis_ijazah: ARSIP nomor ijazah negara — input manual TU.
 *
 * status_santri.file_surat_path: arsip surat mutasi keluar (serve via /_tenant-storage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alumni', function (Blueprint $table) {
            $table->date('tanggal_lulus')->nullable()->after('tahun_lulus');
            $table->foreignId('lembaga_id')->nullable()->after('tanggal_lulus')
                ->constrained('lembaga')->nullOnDelete();
            $table->string('nomor_syahadah')->nullable()->after('lembaga_id');
            $table->unsignedInteger('nomor_syahadah_urut')->nullable()->after('nomor_syahadah');
            $table->string('nomor_ijazah')->nullable()->after('nomor_syahadah_urut')
                ->comment('Nomor ijazah negara — arsip, input manual TU');
            $table->string('jenis_ijazah')->nullable()->after('nomor_ijazah')
                ->comment('mis. Ijazah MTs, Ijazah Paket B, SKL');
        });

        // Backstop balapan penomoran syahadah (pola SuratService):
        // dua generate bersamaan pada urut sama → 23505 → retry sekali.
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS alumni_nomor_syahadah_urut_unik
             ON alumni (tenant_id, tahun_lulus, nomor_syahadah_urut)
             WHERE nomor_syahadah_urut IS NOT NULL'
        );

        Schema::table('status_santri', function (Blueprint $table) {
            $table->string('file_surat_path')->nullable()->after('alasan')
                ->comment('Arsip surat (mis. surat mutasi keluar) — serve via /_tenant-storage');
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS alumni_nomor_syahadah_urut_unik');

        Schema::table('alumni', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lembaga_id');
            $table->dropColumn(['tanggal_lulus', 'nomor_syahadah', 'nomor_syahadah_urut', 'nomor_ijazah', 'jenis_ijazah']);
        });

        Schema::table('status_santri', function (Blueprint $table) {
            $table->dropColumn('file_surat_path');
        });
    }
};
