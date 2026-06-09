<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2-10 — Employee Master + Salary Structure. Master pegawai + komponen gaji
 * konfigurabel (tetap/tunjangan/potongan). PayrollService MEMBACA struktur ini
 * (math slip lama tak diubah). Additif & tenant-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('users_id')->nullable()->constrained('users')->nullOnDelete(); // login PTK (utk slip)
            $table->string('nip', 40)->nullable();
            $table->string('nama');
            $table->string('jabatan')->nullable();
            $table->string('status_kepegawaian', 20)->default('honorer'); // tetap/honorer/kontrak
            $table->string('npwp', 30)->nullable();
            $table->string('ptkp_status', 5)->default('TK0');             // TK0..K3 (utk PPh21 G2-12)
            $table->date('tanggal_masuk')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'nip']);
            $table->index(['tenant_id', 'aktif']);
        });

        Schema::create('komponen_gaji', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('employee_id')->constrained('employee')->cascadeOnDelete();
            $table->string('nama');                              // Honor Pokok, Tunjangan Jabatan, ...
            $table->string('tipe', 12)->default('tetap');        // tetap/tunjangan/potongan
            $table->decimal('nominal', 15, 2)->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komponen_gaji');
        Schema::dropIfExists('employee');
    }
};
