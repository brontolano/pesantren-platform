<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-09 — Modul disiplin/ta'zir berbasis poin (Blueprint Akademik A-4).
 *
 * 4 tabel: master jenis_pelanggaran (poin negatif = prestasi/reward),
 * pelanggaran_santri (alur dicatat→diverifikasi→sanksi_dijalankan→selesai,
 * bisa dibatalkan), sanksi_santri (eksekusi sanksi), eskalasi_disiplin
 * (record SP per santri×periode×label — unique = idempoten di level DB).
 *
 * Akumulasi poin = QUERY (bukan kolom) — lihat DisiplinService::totalPoin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_pelanggaran', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama');
            $table->string('kategori', 20); // ringan|sedang|berat|prestasi
            $table->integer('poin'); // positif = pelanggaran; negatif = prestasi (penyeimbang)
            $table->string('sanksi_default')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'nama'], 'jenis_pelanggaran_tenant_nama_unique');
        });

        Schema::create('pelanggaran_santri', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->foreignId('jenis_pelanggaran_id')->constrained('jenis_pelanggaran')->restrictOnDelete();
            $table->timestamp('waktu_kejadian');
            $table->string('lokasi')->nullable();
            $table->text('kronologi');
            $table->string('foto_bukti')->nullable(); // path disk tenant — serve via /_tenant-storage
            $table->foreignId('pencatat_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 25)->default('dicatat'); // dicatat|diverifikasi|sanksi_dijalankan|selesai|dibatalkan
            $table->foreignId('verifikator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('alasan_batal')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'santri_id', 'waktu_kejadian'], 'pelanggaran_santri_tenant_santri_waktu_index');
            $table->index(['tenant_id', 'status'], 'pelanggaran_santri_tenant_status_index');
        });

        Schema::create('sanksi_santri', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('pelanggaran_id')->constrained('pelanggaran_santri')->cascadeOnDelete();
            $table->string('jenis_sanksi');
            $table->foreignId('pelaksana_id')->constrained('users')->restrictOnDelete();
            $table->date('tanggal');
            $table->string('bukti')->nullable(); // path disk tenant — serve via /_tenant-storage
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'pelanggaran_id'], 'sanksi_santri_tenant_pelanggaran_index');
        });

        Schema::create('eskalasi_disiplin', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->string('periode', 30); // kunci periode poin (mis. 2026-S1 / 2026 / akumulasi)
            $table->string('label', 50);   // label ambang dari Setting (mis. SP1/SP2)
            $table->integer('poin');       // total poin saat eskalasi terjadi
            $table->date('tanggal');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            // Jaring idempoten level DB: satu label SP per santri per periode.
            $table->unique(['tenant_id', 'santri_id', 'periode', 'label'], 'eskalasi_disiplin_sekali_per_periode_unique');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE jenis_pelanggaran ADD CONSTRAINT jenis_pelanggaran_kategori_check CHECK (kategori IN ('ringan','sedang','berat','prestasi'))");
            // Prestasi WAJIB poin <= 0 (mengurangi total); pelanggaran WAJIB poin >= 0.
            DB::statement("ALTER TABLE jenis_pelanggaran ADD CONSTRAINT jenis_pelanggaran_poin_arah_check CHECK ((kategori = 'prestasi' AND poin <= 0) OR (kategori <> 'prestasi' AND poin >= 0))");
            DB::statement("ALTER TABLE pelanggaran_santri ADD CONSTRAINT pelanggaran_santri_status_check CHECK (status IN ('dicatat','diverifikasi','sanksi_dijalankan','selesai','dibatalkan'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eskalasi_disiplin');
        Schema::dropIfExists('sanksi_santri');
        Schema::dropIfExists('pelanggaran_santri');
        Schema::dropIfExists('jenis_pelanggaran');
    }
};
