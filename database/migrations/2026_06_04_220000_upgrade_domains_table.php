<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table): void {
            // type: subdomain (default/fallback), website (domain utama publik), sistem (panel admin)
            $table->string('type')->default('subdomain')->after('tenant_id');
            $table->boolean('is_primary')->default(false)->after('type');
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'type']);
            $table->dropColumn(['type', 'is_primary']);
        });
    }
};
