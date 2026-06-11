<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-13 — Loop Bisyaroh (1/2): tarif khidmah per tenant + ekstensi tabel
 * khidmah agar tertelusur ke sumber (JurnalMengajar/PresensiStaf).
 *
 * (1) `tarif_khidmah`: tarif poin per (jabatan × jenis_sesi). jabatan_id NULL
 *     = tarif default tenant (fallback). Partial unique HANYA baris aktif —
 *     dipecah dua index karena Postgres menganggap dua NULL berbeda pada
 *     unique komposit (pola jurnal_mengajar).
 * (2) `khidmah` + jenis_sesi, sumber morph, is_manual. Baris lama (input
 *     manual G2-11) di-backfill is_manual=true agar TIDAK ikut terhapus oleh
 *     agregasi ulang (aggregator hanya hapus-tulis-ulang is_manual=false).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarif_khidmah', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('jabatan_id')->nullable()->constrained('jabatan')->restrictOnDelete(); // NULL = tarif default tenant
            $table->string('jenis_sesi', 20); // jam_formal|sesi_diniyah|sesi_tahfidz|malam_musyrif|hadir_kantor
            $table->decimal('poin', 12, 2)->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'aktif']);
        });

        Schema::table('khidmah', function (Blueprint $table): void {
            $table->string('jenis_sesi', 20)->nullable()->after('periode');
            $table->string('sumber_type')->nullable()->after('poin');
            $table->unsignedBigInteger('sumber_id')->nullable()->after('sumber_type');
            $table->boolean('is_manual')->default(false)->after('sumber_id');

            $table->index(['sumber_type', 'sumber_id']);
            $table->index(['tenant_id', 'periode', 'is_manual']);
        });

        // Baris khidmah lama = input manual bendahara (G2-11) — lindungi dari
        // hapus-tulis-ulang aggregator.
        DB::table('khidmah')->whereNull('sumber_type')->update(['is_manual' => true]);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tarif_khidmah ADD CONSTRAINT tarif_khidmah_jenis_sesi_check CHECK (jenis_sesi IN ('jam_formal','sesi_diniyah','sesi_tahfidz','malam_musyrif','hadir_kantor'))");
            DB::statement("ALTER TABLE khidmah ADD CONSTRAINT khidmah_jenis_sesi_check CHECK (jenis_sesi IS NULL OR jenis_sesi IN ('jam_formal','sesi_diniyah','sesi_tahfidz','malam_musyrif','hadir_kantor'))");
            // Satu tarif AKTIF per (tenant, jabatan, jenis) — riwayat nonaktif bebas.
            DB::statement('CREATE UNIQUE INDEX tarif_khidmah_jabatan_jenis_aktif_unique ON tarif_khidmah (tenant_id, jabatan_id, jenis_sesi) WHERE aktif AND jabatan_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX tarif_khidmah_default_jenis_aktif_unique ON tarif_khidmah (tenant_id, jenis_sesi) WHERE aktif AND jabatan_id IS NULL');
        }
    }

    public function down(): void
    {
        Schema::table('khidmah', function (Blueprint $table): void {
            $table->dropIndex(['sumber_type', 'sumber_id']);
            $table->dropIndex(['tenant_id', 'periode', 'is_manual']);
            $table->dropColumn(['jenis_sesi', 'sumber_type', 'sumber_id', 'is_manual']);
        });

        Schema::dropIfExists('tarif_khidmah');
    }
};
