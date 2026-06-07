<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel referensi wilayah Indonesia (BPS) di dalam tenant DB.
// Data diisi via: php artisan wilayah:import-tenant {tenant}
//
// Migration ini dijalankan baik oleh `migrate` (central) maupun
// `tenants:migrate` (tiap tenant). Di central, tabel wilayah sudah dibuat
// oleh migration 2026_06_02_140000_create_wilayah_tables — maka di sini
// di-guard dengan Schema::hasTable() agar tidak konflik (no-op di central,
// benar-benar dibuat di tenant DB).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provinces')) {
            Schema::create('provinces', function (Blueprint $table) {
                $table->char('id', 2)->primary();
                $table->string('name', 100);
            });
        }

        if (! Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $table) {
                $table->char('id', 4)->primary();
                $table->char('province_id', 2)->index();
                $table->string('name', 100);
            });
        }

        if (! Schema::hasTable('districts')) {
            Schema::create('districts', function (Blueprint $table) {
                $table->char('id', 7)->primary();
                $table->char('city_id', 4)->index();
                $table->string('name', 100);
            });
        }

        if (! Schema::hasTable('villages')) {
            Schema::create('villages', function (Blueprint $table) {
                $table->char('id', 10)->primary();
                $table->char('district_id', 7)->index();
                $table->string('name', 100);
            });
        }
    }

    public function down(): void
    {
        // Hanya drop bila tabel ini memang dibuat untuk tenant.
        // Di central, biarkan tabel wilayah milik migration central.
        Schema::dropIfExists('villages');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');
    }
};
