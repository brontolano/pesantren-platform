<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-04 — Perluasan data pegawai (EMIS-ready), Blueprint SDM S-0 poin 3.
 *
 * ALTER `employee` (additif, NULL-able/default — baris lama tetap sah):
 * nik, nuptk, nrg, is_mukim, tanggal_selesai (resign/purna — informasi,
 * kolom `aktif` TETAP sumber kebenaran status), pendidikan_terakhir,
 * asal_alumni (temuan riset: rekrutmen dominan alumni).
 *
 * status_kepegawaian: kolom string bebas TANPA CHECK constraint (validasi
 * app-level via Employee::STATUS_KEPEGAWAIAN) — cukup migrasi data map nilai
 * lama ke taksonomi baru: tetap→gty, honorer→gtt_honorer, kontrak→kontrak.
 * Mapping dipisah ke method publik agar bisa diuji langsung dari test.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee', function (Blueprint $table): void {
            // NIK KTP (EMIS) — UNIQUE NULL-safe: pgsql & sqlite menganggap NULL
            // saling beda, jadi banyak baris tanpa NIK tetap sah; duplikat NIK
            // terisi ditolak di DB (impor Excel bypass form — validasi form saja
            // tidak cukup). Kolom baru → mustahil ada duplikat eksisting.
            $table->string('nik', 20)->nullable()->unique();
            $table->string('nuptk', 20)->nullable();               // Nomor Unik PTK (EMIS)
            $table->string('nrg', 20)->nullable();                 // Nomor Registrasi Guru (sertifikasi)
            $table->boolean('is_mukim')->default(false);           // tinggal di pondok
            $table->date('tanggal_selesai')->nullable();           // resign/purna — informasi, `aktif` tetap sumber kebenaran
            $table->string('pendidikan_terakhir')->nullable();     // SMA/MA, S1, S2, pesantren, ...
            $table->boolean('asal_alumni')->default(false);        // alumni pesantren ini
        });

        // Default baru utk baris yang dibuat tanpa status eksplisit.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE employee ALTER COLUMN status_kepegawaian SET DEFAULT 'gtt_honorer'");
        }

        $this->mapStatusKepegawaianLama();
    }

    /**
     * Map nilai status lama → taksonomi baru. Idempoten (nilai baru tak
     * tersentuh; nilai tak dikenal DIBIARKAN — jangan rusak data, validasi
     * app-level akan menampilkannya apa adanya).
     */
    public function mapStatusKepegawaianLama(): void
    {
        DB::table('employee')->where('status_kepegawaian', 'tetap')->update(['status_kepegawaian' => 'gty']);
        DB::table('employee')->where('status_kepegawaian', 'honorer')->update(['status_kepegawaian' => 'gtt_honorer']);
        // 'kontrak' → 'kontrak' (tetap, tak perlu update).
    }

    public function down(): void
    {
        // Map balik sebisanya (pengabdian/pns_dpk/pppk tak punya padanan lama → biarkan).
        DB::table('employee')->where('status_kepegawaian', 'gty')->update(['status_kepegawaian' => 'tetap']);
        DB::table('employee')->where('status_kepegawaian', 'gtt_honorer')->update(['status_kepegawaian' => 'honorer']);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE employee ALTER COLUMN status_kepegawaian SET DEFAULT 'honorer'");
        }

        Schema::table('employee', function (Blueprint $table): void {
            // SQLite menolak DROP COLUMN yang masih diindeks — lepas dulu.
            $table->dropUnique(['nik']);
            $table->dropColumn(['nik', 'nuptk', 'nrg', 'is_mukim', 'tanggal_selesai', 'pendidikan_terakhir', 'asal_alumni']);
        });
    }
};
