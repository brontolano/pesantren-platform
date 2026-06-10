<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Persetujuan Kebijakan Privasi Sistem saat daftar PPDB (UU PDP). Additif. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('calon_santri') && ! Schema::hasColumn('calon_santri', 'consent_kebijakan_at')) {
            Schema::table('calon_santri', function (Blueprint $table): void {
                $table->timestamp('consent_kebijakan_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('calon_santri') && Schema::hasColumn('calon_santri', 'consent_kebijakan_at')) {
            Schema::table('calon_santri', fn (Blueprint $table) => $table->dropColumn('consent_kebijakan_at'));
        }
    }
};
