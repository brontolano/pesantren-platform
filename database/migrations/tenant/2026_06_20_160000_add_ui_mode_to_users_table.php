<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'ui_mode')) {
            Schema::table('users', function (Blueprint $table): void {
                // 'simple' | null/'advanced' — preferensi tampilan panel per user.
                $table->string('ui_mode', 10)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'ui_mode')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('ui_mode'));
        }
    }
};
