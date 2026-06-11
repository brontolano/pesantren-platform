<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi_asrama', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('slot', 30);
            $table->string('status', 15); // hadir|alpha|izin|sakit|terlambat
            $table->text('keterangan')->nullable();
            $table->foreignId('pencatat_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('kamar_id')->nullable()->constrained('kamar_asrama')->nullOnDelete();
            $table->foreignId('halaqah_id')->nullable()->constrained('halaqah')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'santri_id', 'tanggal', 'slot'], 'presensi_asrama_unik');
            $table->index(['tenant_id', 'tanggal', 'slot'], 'presensi_asrama_tanggal_slot_idx');
            $table->index(['tenant_id', 'santri_id', 'tanggal'], 'presensi_asrama_santri_tanggal_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE presensi_asrama ADD CONSTRAINT presensi_asrama_status_check CHECK (status IN ('hadir','alpha','izin','sakit','terlambat'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi_asrama');
    }
};
