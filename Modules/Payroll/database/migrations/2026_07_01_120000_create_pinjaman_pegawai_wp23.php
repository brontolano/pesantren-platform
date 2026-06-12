<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-23 — Kasbon / Pinjaman Pegawai (MONEY WP).
 *
 * - pinjaman_pegawai: pengajuan kasbon per pegawai. Status lifecycle:
 *   diajukan → disetujui → berjalan (setelah pencairan) → lunas; atau batal.
 *   CHECK pgsql menjaga nominal > 0, cicilan_bulanan > 0, sisa_pokok >= 0.
 * - slip_gaji.potongan_kasbon: ADDITIF (default 0) — diisi hanya oleh
 *   PayrollService::terapkanPotongan(); slip lama & math existing tak berubah
 *   satu rupiah pun (pola potongan_cuti WP-14 persis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pinjaman_pegawai', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('employee_id')->constrained('employee')->cascadeOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->date('tanggal');
            $table->decimal('cicilan_bulanan', 15, 2);
            // Saldo berjalan; berkurang HANYA saat slip FINAL / pelunasan dipercepat.
            $table->decimal('sisa_pokok', 15, 2);
            $table->string('status', 12)->default('diajukan');
            $table->unsignedBigInteger('jurnal_pencairan_id')->nullable();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE pinjaman_pegawai ADD CONSTRAINT pinjaman_pegawai_status_check CHECK (status IN ('diajukan','disetujui','berjalan','lunas','batal'))");
            DB::statement('ALTER TABLE pinjaman_pegawai ADD CONSTRAINT pinjaman_pegawai_nominal_check CHECK (nominal > 0)');
            DB::statement('ALTER TABLE pinjaman_pegawai ADD CONSTRAINT pinjaman_pegawai_cicilan_check CHECK (cicilan_bulanan > 0)');
            DB::statement('ALTER TABLE pinjaman_pegawai ADD CONSTRAINT pinjaman_pegawai_sisa_check CHECK (sisa_pokok >= 0)');
        }

        Schema::table('slip_gaji', function (Blueprint $table) {
            if (! Schema::hasColumn('slip_gaji', 'potongan_kasbon')) {
                $table->decimal('potongan_kasbon', 15, 2)->default(0)->after('potongan_cuti');
            }
        });
    }

    public function down(): void
    {
        Schema::table('slip_gaji', function (Blueprint $table) {
            if (Schema::hasColumn('slip_gaji', 'potongan_kasbon')) {
                $table->dropColumn('potongan_kasbon');
            }
        });

        Schema::dropIfExists('pinjaman_pegawai');
    }
};
