<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2-11 — Attendance (presensi staf) + Khidmah (poin layanan) + Leave (cuti
 * dengan approval F1). Untuk STAF/Employee — terpisah dari PresensiKegiatan
 * (Modules/Kegiatan) yang tetap dipakai. Additif & tenant-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi_staf', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('employee_id')->constrained('employee')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('status', 10)->default('hadir');  // hadir/izin/sakit/alpa/cuti
            $table->time('jam_masuk')->nullable();
            $table->time('jam_keluar')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'employee_id', 'tanggal'], 'presensi_staf_unik');
            $table->index(['tenant_id', 'tanggal']);
        });

        Schema::create('khidmah', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('employee_id')->constrained('employee')->cascadeOnDelete();
            $table->string('periode', 7);                     // 2026-06
            $table->decimal('poin', 8, 2)->default(0);        // poin layanan/khidmah
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id', 'periode']);
        });

        Schema::create('cuti', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('employee_id')->constrained('employee')->cascadeOnDelete();
            $table->string('jenis', 20)->default('tahunan');  // tahunan/sakit/penting/melahirkan/lainnya
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->integer('jumlah_hari')->default(1);
            $table->string('alasan')->nullable();
            $table->string('status', 12)->default('diajukan'); // diajukan/disetujui/ditolak
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_pada')->nullable();
            $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti');
        Schema::dropIfExists('khidmah');
        Schema::dropIfExists('presensi_staf');
    }
};
