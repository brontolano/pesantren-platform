<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Akun login santri (App Santri): tautan santri -> users. Additif & guarded. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('santri') || Schema::hasColumn('santri', 'user_id')) {
            return;
        }

        Schema::table('santri', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('wali_user_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('santri', 'user_id')) {
            Schema::table('santri', fn (Blueprint $t) => $t->dropColumn('user_id'));
        }
    }
};
