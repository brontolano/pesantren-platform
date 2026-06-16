<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Siklus hidup modul (self-installer): kapan modul DIPASANG (installed_at) dan
 * kapan SIAP/terkonfigurasi (configured_at). Additive — is_active TETAP sumber
 * visibilitas yang dibaca ModuleService.
 *
 * Backfill SENGAJA hanya installed_at (jejak audit: modul aktif = sudah dipasang).
 * configured_at dibiarkan NULL → kesiapan dihitung LIVE (ModuleSetupMap::ready),
 * sehingga tenant lama otomatis ramping & AMAN: modul yang sudah berisi data
 * tetap tampil, modul aktif-tapi-kosong dirapikan tanpa kehilangan data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_modules', function (Blueprint $table) {
            $table->timestamp('installed_at')->nullable()->after('is_active');
            $table->timestamp('configured_at')->nullable()->after('installed_at');
        });

        DB::table('tenant_modules')
            ->where('is_active', true)
            ->whereNull('installed_at')
            ->update(['installed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('tenant_modules', function (Blueprint $table) {
            $table->dropColumn(['installed_at', 'configured_at']);
        });
    }
};
