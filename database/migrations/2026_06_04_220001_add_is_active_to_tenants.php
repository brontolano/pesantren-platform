<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // is_active sudah ada dari migrasi tenants awal, tapi pastikan ada
        if (! Schema::hasColumn('tenants', 'is_active')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->boolean('is_active')->default(true)->after('name');
            });
        }
    }

    public function down(): void {}
};
