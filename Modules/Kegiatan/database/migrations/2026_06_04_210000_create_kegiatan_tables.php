<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->string('jenis')->default('umum'); // umum, wajib, sunnah
            $table->dateTime('mulai');
            $table->dateTime('selesai')->nullable();
            $table->string('lokasi')->nullable();
            $table->string('status')->default('terjadwal'); // terjadwal, berlangsung, selesai, dibatalkan
            $table->foreignId('penanggung_jawab_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'mulai']);
        });

        Schema::create('presensi_kegiatan', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->string('status')->default('hadir'); // hadir, izin, alpha, sakit
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'kegiatan_id', 'santri_id']);
            $table->index(['tenant_id', 'santri_id']);
        });

        Schema::create('izin_santri', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->string('jenis_izin'); // pulang, sakit, keluar_kota, kunjungan
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan');
            $table->string('status')->default('pending'); // pending, disetujui, ditolak
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan_pengurus')->nullable();
            $table->boolean('sudah_kembali')->default(false);
            $table->dateTime('tanggal_kembali_aktual')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'santri_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('kunjungan_wali', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->string('nama_pengunjung');
            $table->string('hubungan'); // ayah, ibu, saudara, wali
            $table->date('tanggal');
            $table->time('jam_datang')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->text('keperluan')->nullable();
            $table->string('status')->default('hadir'); // hadir, izin_ditolak
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'santri_id']);
            $table->index(['tenant_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kunjungan_wali');
        Schema::dropIfExists('izin_santri');
        Schema::dropIfExists('presensi_kegiatan');
        Schema::dropIfExists('kegiatan');
    }
};
