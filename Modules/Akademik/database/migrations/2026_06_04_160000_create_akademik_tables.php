<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_akademik', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama');
            $table->string('tahun_akademik', 20);
            $table->string('semester', 20)->default('ganjil');
            $table->date('tanggal_mulai');
            $table->date('tanggal_akhir');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'is_active']);
            $table->unique(['tenant_id', 'tahun_akademik', 'semester'], 'periode_akademik_tenant_tahun_semester_unique');
        });

        Schema::create('pelajaran', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama');
            $table->string('kode', 50);
            $table->unsignedSmallInteger('sks')->default(1);
            $table->foreignId('guru_pengampu_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'guru_pengampu_id']);
            $table->unique(['tenant_id', 'kode']);
        });

        Schema::create('jadwal_pelajaran', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('pelajaran_id')->constrained('pelajaran')->cascadeOnDelete();
            $table->foreignId('periode_akademik_id')->nullable()->constrained('periode_akademik')->nullOnDelete();
            $table->string('hari', 20);
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('ruangan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'kelas_id', 'hari']);
            $table->index(['tenant_id', 'pelajaran_id']);
        });

        Schema::create('nilai', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->foreignId('pelajaran_id')->constrained('pelajaran')->cascadeOnDelete();
            $table->foreignId('periode_akademik_id')->constrained('periode_akademik')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->decimal('nilai_harian', 5, 2)->nullable();
            $table->decimal('nilai_uts', 5, 2)->nullable();
            $table->decimal('nilai_uas', 5, 2)->nullable();
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'santri_id', 'pelajaran_id', 'periode_akademik_id'], 'nilai_tenant_santri_pelajaran_periode_unique');
            $table->index(['tenant_id', 'kelas_id']);
            $table->index(['tenant_id', 'pelajaran_id']);
        });

        Schema::create('rapor', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->foreignId('periode_akademik_id')->constrained('periode_akademik')->cascadeOnDelete();
            $table->decimal('total_sks', 8, 2)->default(0);
            $table->decimal('ipk', 8, 2)->default(0);
            $table->unsignedInteger('rank_kelas')->nullable();
            $table->text('catatan_walikelas')->nullable();
            $table->json('ringkasan_nilai')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'santri_id', 'periode_akademik_id'], 'rapor_tenant_santri_periode_unique');
            $table->index(['tenant_id', 'periode_akademik_id']);
            $table->index(['tenant_id', 'rank_kelas']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor');
        Schema::dropIfExists('nilai');
        Schema::dropIfExists('jadwal_pelajaran');
        Schema::dropIfExists('pelajaran');
        Schema::dropIfExists('periode_akademik');
    }
};
