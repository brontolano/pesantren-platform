<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah alur APPROVAL (state-machine) + VERSIONING ke modul Mutu (#27).
 * ADITIF — tidak mengubah migrasi pembuat tabel yang sudah jalan di produksi.
 *
 * status_approval: draft → diajukan → disetujui | ditolak.
 * dokumen_mutu_versi: snapshot tiap versi (riwayat dokumen mutu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumen_mutu', function (Blueprint $table) {
            $table->string('status_approval', 20)->default('draft')->after('status_kelengkapan');
            $table->unsignedInteger('versi')->default(1)->after('status_approval');
            $table->foreignId('diajukan_oleh')->nullable()->after('versi')->constrained('users')->nullOnDelete();
            $table->timestamp('diajukan_pada')->nullable()->after('diajukan_oleh');
            $table->foreignId('direview_oleh')->nullable()->after('diajukan_pada')->constrained('users')->nullOnDelete();
            $table->timestamp('direview_pada')->nullable()->after('direview_oleh');
            $table->text('catatan_review')->nullable()->after('direview_pada');

            $table->index(['tenant_id', 'status_approval']);
        });

        Schema::create('dokumen_mutu_versi', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('dokumen_mutu_id')->constrained('dokumen_mutu')->cascadeOnDelete();
            $table->unsignedInteger('versi');
            $table->string('judul');
            $table->string('jenis', 30);
            $table->string('regulasi_acuan')->nullable();
            $table->string('berkas_url')->nullable();
            $table->string('status_approval', 20);
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['dokumen_mutu_id', 'versi']);
            $table->index(['tenant_id', 'dokumen_mutu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_mutu_versi');

        Schema::table('dokumen_mutu', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diajukan_oleh');
            $table->dropConstrainedForeignId('direview_oleh');
            $table->dropColumn(['status_approval', 'versi', 'diajukan_pada', 'direview_pada', 'catatan_review']);
        });
    }
};
