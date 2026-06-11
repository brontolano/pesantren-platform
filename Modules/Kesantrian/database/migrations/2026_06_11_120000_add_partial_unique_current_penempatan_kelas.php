<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-05 (a) — Jaminan DB-level: maksimal SATU penempatan aktif per santri.
 *
 * Urutan WAJIB dalam satu migrasi:
 * 1. Bersihkan data lama yang sempat punya >1 baris is_current per santri
 *    (pertahankan yang terbaru: tanggal_penempatan DESC, id DESC).
 * 2. Baru pasang partial unique index — tanpa langkah 1, CREATE UNIQUE INDEX
 *    gagal di tenant yang punya data dobel-current.
 *
 * Hanya Postgres yang mendukung partial index; di driver lain (mis. SQLite
 * pendukung partial index juga, MySQL tidak) kita lewati index-nya —
 * observer tetap menjadi penjaga logis.
 */
return new class extends Migration
{
    private const INDEX = 'penempatan_kelas_santri_current_unique';

    public function up(): void
    {
        if (! Schema::hasTable('penempatan_kelas')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        // 1) Dedupe dobel-current: pertahankan baris terbaru per santri.
        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                UPDATE penempatan_kelas
                SET is_current = false
                WHERE is_current = true
                  AND id NOT IN (
                    SELECT DISTINCT ON (santri_id) id
                    FROM penempatan_kelas
                    WHERE is_current = true
                    ORDER BY santri_id, tanggal_penempatan DESC, id DESC
                  )
            SQL);

            // 2) Partial unique index.
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX IF NOT EXISTS %s ON penempatan_kelas (santri_id) WHERE is_current',
                self::INDEX,
            ));

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement(<<<'SQL'
                UPDATE penempatan_kelas
                SET is_current = 0
                WHERE is_current = 1
                  AND id NOT IN (
                    SELECT MAX(id)
                    FROM penempatan_kelas
                    WHERE is_current = 1
                    GROUP BY santri_id
                  )
            SQL);

            DB::statement(sprintf(
                'CREATE UNIQUE INDEX IF NOT EXISTS %s ON penempatan_kelas (santri_id) WHERE is_current = 1',
                self::INDEX,
            ));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('penempatan_kelas')) {
            return;
        }

        DB::statement(sprintf('DROP INDEX IF EXISTS %s', self::INDEX));
    }
};
