<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-03 — Master Jabatan + Penugasan Multi-Peran (Blueprint SDM S-0 poin 1-2-4-5).
 *
 * (1) `jabatan`: master per tenant — nama, kategori (CHECK pgsql), role_spatie
 *     NULL (mapping ke role yang SUDAH ada; tidak menciptakan role baru),
 *     aktif. Seeder ±15 jabatan riset via JabatanSeeder (TenantProvisioner).
 * (2) `penugasan_pegawai`: pengganti kolom Employee.jabatan (string) — satu
 *     pegawai banyak jabatan di banyak lembaga; lembaga_id NULL = lintas
 *     lembaga; tanggal_selesai NULL = aktif; riwayat = baris non-aktif.
 *     Partial unique: hanya SATU is_primary aktif per pegawai (pola partial
 *     index periode_akademik WP-01).
 *
 * Kolom Employee.jabatan TIDAK di-drop (deprecated, read-only di form) —
 * ALTER employees = WP-04 terpisah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jabatan', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama');
            $table->string('kategori', 20)->default('staf'); // struktural|pengajar|kepengasuhan|pengurus_santri|staf
            $table->string('role_spatie')->nullable();        // nama role Spatie yang SUDAH ada (NULL = tanpa akses otomatis)
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'aktif']);
            $table->unique(['tenant_id', 'nama']);
        });

        Schema::create('penugasan_pegawai', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('employee_id')->constrained('employee')->cascadeOnDelete();
            $table->foreignId('jabatan_id')->constrained('jabatan')->restrictOnDelete(); // jabatan terpakai tak boleh hilang
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->nullOnDelete(); // NULL = lintas lembaga
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable(); // NULL = penugasan aktif
            $table->string('nomor_sk')->nullable();
            $table->string('file_sk')->nullable();
            $table->boolean('is_primary')->default(false); // jabatan utama (tampilan)
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'employee_id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE jabatan ADD CONSTRAINT jabatan_kategori_check CHECK (kategori IN ('struktural','pengajar','kepengasuhan','pengurus_santri','staf'))");
            DB::statement('ALTER TABLE penugasan_pegawai ADD CONSTRAINT penugasan_tanggal_check CHECK (tanggal_selesai IS NULL OR tanggal_selesai >= tanggal_mulai)');
            // Hanya SATU jabatan utama AKTIF per pegawai. Partial index —
            // baris yang sudah ditutup (riwayat) bebas (pola WP-01).
            DB::statement('CREATE UNIQUE INDEX penugasan_primary_aktif_unique ON penugasan_pegawai (tenant_id, employee_id) WHERE is_primary AND tanggal_selesai IS NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS penugasan_primary_aktif_unique');
        }

        Schema::dropIfExists('penugasan_pegawai');
        Schema::dropIfExists('jabatan');
    }
};
