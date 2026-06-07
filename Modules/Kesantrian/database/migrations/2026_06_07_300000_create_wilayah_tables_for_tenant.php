<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel referensi wilayah Indonesia (BPS) di dalam tenant DB.
// Data diisi via: php artisan wilayah:import-tenant {tenant}
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->char('id', 2)->primary();
            $table->string('name', 100);
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->char('id', 4)->primary();
            $table->char('province_id', 2)->index();
            $table->string('name', 100);
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->char('id', 7)->primary();
            $table->char('city_id', 4)->index();
            $table->string('name', 100);
        });

        Schema::create('villages', function (Blueprint $table) {
            $table->char('id', 10)->primary();
            $table->char('district_id', 7)->index();
            $table->string('name', 100);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villages');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');
    }
};
