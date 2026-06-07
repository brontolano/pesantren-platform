<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom SEO ke tabel galleries (sejajar dengan articles).
 *
 * Migration modul CMS dijalankan di central (`migrate`) maupun tiap tenant
 * (`tenants:migrate`). Di-guard hasColumn() agar idempotent & aman dijalankan
 * ulang (mencegah error duplicate column di mana pun tabel sudah dimodifikasi).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('galleries')) {
            return;
        }

        Schema::table('galleries', function (Blueprint $table) {
            if (! Schema::hasColumn('galleries', 'meta_title')) {
                $table->string('meta_title', 70)->nullable()->after('description');
            }
            if (! Schema::hasColumn('galleries', 'meta_description')) {
                $table->string('meta_description', 160)->nullable()->after('meta_title');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('galleries')) {
            return;
        }

        Schema::table('galleries', function (Blueprint $table) {
            foreach (['meta_title', 'meta_description'] as $col) {
                if (Schema::hasColumn('galleries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
