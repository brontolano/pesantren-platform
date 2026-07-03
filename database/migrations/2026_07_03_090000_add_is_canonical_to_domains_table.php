<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table): void {
            // Domain kanonis per-tenant (OPT-IN, default false = perilaku lama).
            // Bila salah satu domain tenant ditandai kanonis, request web GET/HEAD
            // ke domain lain BER-TYPE SAMA milik tenant tsb di-redirect 301 ke sini.
            // JANGAN samakan dengan is_primary (is_primary = domain website publik).
            $table->boolean('is_canonical')->default(false)->after('is_primary');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table): void {
            $table->dropColumn('is_canonical');
        });
    }
};
