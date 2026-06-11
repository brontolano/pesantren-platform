<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F0-PWA — Domain kajian kitab (sorogan/bandongan/wetonan).
 *
 * capaian_kitab.santri_id NULL = posisi KELOMPOK (bandongan/wetonan);
 * terisi = capaian per santri (sorogan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitab', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama');
            $table->string('pengarang')->nullable();
            $table->string('kategori')->nullable(); // fan: fiqih, nahwu, akhlak, ...
            $table->unsignedInteger('jumlah_halaman')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
        });

        Schema::create('kajian_kitab', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('kitab_id')->constrained('kitab')->cascadeOnDelete();
            $table->foreignId('pengampu_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('halaqah_id')->nullable()->constrained('halaqah')->nullOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->foreignId('periode_akademik_id')->nullable()->constrained('periode_akademik')->nullOnDelete();
            $table->string('metode', 20)->default('sorogan'); // sorogan|bandongan|wetonan
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'pengampu_id']);
            $table->index(['tenant_id', 'kitab_id']);
        });

        Schema::create('capaian_kitab', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('kajian_kitab_id')->constrained('kajian_kitab')->cascadeOnDelete();
            // NULL = posisi kelompok (bandongan). Cascade saat santri dihapus —
            // nullOnDelete akan MENGUBAH makna baris jadi "posisi kelompok".
            $table->foreignId('santri_id')->nullable()->constrained('santri')->cascadeOnDelete();
            $table->string('posisi'); // bab/halaman, bebas: "Bab Thaharah hal. 12"
            $table->date('tanggal');
            $table->string('status', 20)->default('lanjut'); // lanjut|ulang|khatam
            $table->text('catatan')->nullable();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'kajian_kitab_id', 'tanggal']);
            $table->index(['tenant_id', 'santri_id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE kajian_kitab ADD CONSTRAINT kajian_kitab_metode_check CHECK (metode IN ('sorogan','bandongan','wetonan'))");
            DB::statement("ALTER TABLE capaian_kitab ADD CONSTRAINT capaian_kitab_status_check CHECK (status IN ('lanjut','ulang','khatam'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('capaian_kitab');
        Schema::dropIfExists('kajian_kitab');
        Schema::dropIfExists('kitab');
    }
};
