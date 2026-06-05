<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kuota penyimpanan per tenant (CENTRAL only) — platform yang menjual/menetapkan.
 * Default 8 GB; demo 2 GB (di-set terpisah). Pemakaian dihitung dari media_files
 * di DB tenant. Tier 64/128/256 GB menyusul (butuh object storage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_storage', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->unique();
            $table->unsignedInteger('quota_gb')->default(8);
            $table->timestamps();
        });

        // Daftarkan modul File Storage (development = hanya demo dulu).
        DB::table('module_registry')->insertOrIgnore([
            'key' => 'file_storage',
            'label' => 'File Storage',
            'status' => 'development',
            'is_core' => false,
            'sort' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_storage');
        DB::table('module_registry')->where('key', 'file_storage')->delete();
    }
};
