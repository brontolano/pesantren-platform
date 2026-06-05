<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori artikel (untuk website: article | announcement | achievement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('category')->nullable()->default('article');
            $table->index(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'category']);
            $table->dropColumn('category');
        });
    }
};
