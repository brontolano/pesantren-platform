<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sumber_dana', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('kode_dana', 10);                  // 01 BOS/BOP, 02 SPP/Swadaya, 03 Wakaf, 04 Zakat/Infak
            $table->string('nama_sumber');
            $table->string('kategori', 20);                   // negara / komersial / sosial
            // Koreksi #2: pisahkan BOS Madrasah vs BOP Pesantren lewat sub-rezim
            $table->string('rezim', 20)->nullable();          // bos_madrasah / bop_pesantren / null
            $table->boolean('is_wakaf')->default(false);      // krusial: cegah salah agunan (UU 41/2004 Ps 40)
            $table->boolean('is_terikat')->default(false);    // dana terikat (PSAK 112/109) vs tidak terikat
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'kode_dana']);
            $table->index(['tenant_id', 'kategori']);
            $table->index(['tenant_id', 'is_wakaf']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sumber_dana');
    }
};
