<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-12 — Kesiapan EMIS: kolom mukim/tidak pada santri.
 *
 * nik & nisn SUDAH ada di santri sejak 2026_06_07_200000 (lalu dilebarkan
 * 2026_06_07_200100). Yang belum ada hanya penanda mukim (tinggal di pondok)
 * — nullable: NULL = belum didata (beda makna dengan false = tidak mukim).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('santri', function (Blueprint $table): void {
            $table->boolean('is_mukim')->nullable()->after('status')
                ->comment('Mukim (tinggal di pondok); NULL = belum didata');
        });
    }

    public function down(): void
    {
        Schema::table('santri', function (Blueprint $table): void {
            $table->dropColumn('is_mukim');
        });
    }
};
