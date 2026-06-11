<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-15 — THR (slip tipe 'thr').
 *
 * - slip_gaji.tipe: 'bulanan' (default — backfill otomatis utk data existing
 *   via DEFAULT) | 'thr'. CHECK constraint di pgsql.
 * - slip_gaji.tahun_thr: smallint nullable, terisi HANYA untuk tipe='thr'.
 * - Partial unique (tenant_id, users_id, tahun_thr) WHERE tipe='thr' →
 *   satu THR per pegawai per tahun, ditegakkan di DB (bukan cuma app-level).
 *
 * Unique existing (tenant_id, users_id, periode) TIDAK disentuh: slip THR
 * memakai periode 'THR-<tahun>' sehingga tak bentrok slip bulanan 'YYYY-MM'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slip_gaji', function (Blueprint $table) {
            if (! Schema::hasColumn('slip_gaji', 'tipe')) {
                $table->string('tipe', 20)->default('bulanan')->after('periode');
            }
            if (! Schema::hasColumn('slip_gaji', 'tahun_thr')) {
                $table->smallInteger('tahun_thr')->nullable()->after('tipe');
            }
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE slip_gaji ADD CONSTRAINT slip_gaji_tipe_check CHECK (tipe IN ('bulanan', 'thr'))");
            // Invariant uang di DB: tipe='thr' WAJIB ber-tahun_thr (NULL lolos
            // dari partial unique — NULLs distinct di Postgres) dan sebaliknya.
            DB::statement("ALTER TABLE slip_gaji ADD CONSTRAINT slip_gaji_thr_tahun_check CHECK ((tipe = 'thr') = (tahun_thr IS NOT NULL))");
        }

        // Partial unique index — didukung pgsql & sqlite (driver test).
        DB::statement("CREATE UNIQUE INDEX slip_gaji_thr_per_tahun_unique ON slip_gaji (tenant_id, users_id, tahun_thr) WHERE tipe = 'thr'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS slip_gaji_thr_per_tahun_unique');
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE slip_gaji DROP CONSTRAINT IF EXISTS slip_gaji_thr_tahun_check');
            DB::statement('ALTER TABLE slip_gaji DROP CONSTRAINT IF EXISTS slip_gaji_tipe_check');
        }

        Schema::table('slip_gaji', function (Blueprint $table) {
            foreach (['tahun_thr', 'tipe'] as $kolom) {
                if (Schema::hasColumn('slip_gaji', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
