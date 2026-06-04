<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registry modul (CENTRAL only). Menentukan status rilis tiap modul:
 *  - released    : muncul di semua tenant sesuai paket (tenant_modules)
 *  - development : hanya muncul di tenant demo (mode developer)
 *  - is_core     : selalu aktif untuk semua tenant (tak bisa dimatikan)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_registry', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('status')->default('released'); // released | development
            $table->boolean('is_core')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();
        $modules = [
            // key, label, status, is_core, sort
            ['kesantrian', 'Kesantrian', 'released', true, 1],
            ['keuangan', 'Keuangan', 'released', false, 2],
            ['ppdb', 'PPDB', 'released', false, 3],
            ['notifikasi', 'Notifikasi WA', 'released', false, 4],
            ['cms', 'CMS Website', 'released', false, 5],
            ['portal_wali', 'Portal Wali', 'released', false, 6],
            ['akademik', 'Akademik', 'development', false, 7],
            ['asrama', 'Asrama', 'development', false, 8],
            ['tahfidz', 'Tahfidz', 'development', false, 9],
            ['kesehatan', 'Kesehatan & Konseling', 'development', false, 10],
            ['kegiatan', 'Kegiatan & Perizinan', 'development', false, 11],
        ];
        foreach ($modules as [$key, $label, $status, $core, $sort]) {
            DB::table('module_registry')->insert([
                'key' => $key, 'label' => $label, 'status' => $status,
                'is_core' => $core, 'sort' => $sort,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('module_registry');
    }
};
