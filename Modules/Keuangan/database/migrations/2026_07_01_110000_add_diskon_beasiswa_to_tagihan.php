<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2c — Potongan beasiswa dilacak di kolom sendiri (diskon_beasiswa) agar
 * terapkanPada idempoten (re-run = SET ulang, bukan menumpuk ke diskon manual).
 * Additif, aman untuk data live: default 0, tidak mengubah baris existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan', function (Blueprint $table): void {
            $table->decimal('diskon_beasiswa', 15, 2)->default(0)->after('diskon');
        });
    }

    public function down(): void
    {
        Schema::table('tagihan', function (Blueprint $table): void {
            $table->dropColumn('diskon_beasiswa');
        });
    }
};
