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
 * Backfill GRANDFATHER untuk tenant EKSISTING: modul yang SUDAH aktif dianggap
 * sudah "dipasang & siap" (installed_at + configured_at = created_at). Ini AMAN
 * untuk tenant LIVE (asy-syifaa, albakids) — modul yang sedang dipakai tidak
 * berubah status & tetap tampil; perilaku "perlu disiapkan dulu" hanya berlaku
 * untuk modul yang BARU dipasang sendiri lewat Pusat Modul setelah deploy.
 * (Default minimal hanya untuk tenant BARU; lihat TenantProvisioner::seedModules.)
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

        DB::table('tenant_modules')
            ->where('is_active', true)
            ->whereNull('configured_at')
            ->update(['configured_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('tenant_modules', function (Blueprint $table) {
            $table->dropColumn(['installed_at', 'configured_at']);
        });
    }
};
