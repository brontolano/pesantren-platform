<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Selesaikan kontradiksi: ai_credit_ledger punya FK tenant_id cascadeOnDelete
 * TAPI trigger append-only menolak SEMUA delete → penghapusan tenant mustahil
 * (error trigger membingungkan). UPDATE harus tetap mutlak dilarang (imutabilitas
 * baris ledger), tetapi DELETE perlu diizinkan KHUSUS saat purge tenant yang
 * eksplisit & terotorisasi (platform menghapus tenant beserta riwayatnya).
 *
 * Solusi: trigger sadar-konteks via GUC sesi `app.ledger_purge`. DELETE hanya
 * lolos bila `app.ledger_purge = 'on'` di-SET (oleh TenantRecoveryService::purge
 * dalam transaksi). Di luar itu DELETE tetap ditolak, UPDATE selalu ditolak.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection($this->getConnection())->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION ai_credit_ledger_append_only() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    RAISE EXCEPTION 'ai_credit_ledger bersifat append-only: UPDATE tidak diizinkan.';
                END IF;

                -- DELETE hanya untuk purge tenant terotorisasi (GUC app.ledger_purge='on').
                IF TG_OP = 'DELETE'
                   AND current_setting('app.ledger_purge', true) IS DISTINCT FROM 'on' THEN
                    RAISE EXCEPTION 'ai_credit_ledger bersifat append-only: DELETE tidak diizinkan (kecuali purge tenant terotorisasi).';
                END IF;

                RETURN OLD;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    public function down(): void
    {
        if (DB::connection($this->getConnection())->getDriverName() !== 'pgsql') {
            return;
        }

        // Kembalikan ke perilaku tolak-semua (append-only ketat tanpa purge).
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION ai_credit_ledger_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'ai_credit_ledger bersifat append-only: % tidak diizinkan.', TG_OP;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }
};
