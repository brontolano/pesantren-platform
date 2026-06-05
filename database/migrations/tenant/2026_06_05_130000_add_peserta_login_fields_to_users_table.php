<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field login akun PESERTA PPDB pada tabel `users` (Opsi A — Hukum #2 Blueprint:
 * satu sistem auth; peserta = baris `users` + role `peserta`, BUKAN guard baru).
 *
 *  - phone               : identitas login peserta (No. HP ternormalisasi `62…`), unik per DB tenant.
 *  - must_reset_password : paksa ganti password saat login pertama (kredensial sementara dari panitia).
 *  - email               : dijadikan nullable — peserta login via No. HP, tidak wajib email.
 *
 * Dicerminkan di database/migrations (central) & database/migrations/tenant,
 * mengikuti pola migrasi `users` yang sudah ada (add_tenant_id, add_email_authentication).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable()->unique()->after('email');
            }
            if (! Schema::hasColumn('users', 'must_reset_password')) {
                $table->boolean('must_reset_password')->default(false)->after('password');
            }
        });

        // Peserta login pakai No. HP → email tidak wajib.
        // (PostgreSQL mengizinkan banyak NULL pada kolom unique, jadi constraint email tetap aman.)
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropUnique(['phone']);
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('users', 'must_reset_password')) {
                $table->dropColumn('must_reset_password');
            }
        });
        // Kolom email sengaja dibiarkan nullable saat rollback (bisa jadi ada peserta tanpa email).
    }
};
