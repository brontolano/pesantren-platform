<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_mutu', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('judul');
            $table->string('jenis', 30);                 // SOP / Hasil SKM / MoU / SK / Rubrik
            $table->string('regulasi_acuan')->nullable(); // KMA 939/2024, KMA 1495/2025
            $table->string('berkas_url')->nullable();    // path disk tenant (/_tenant-storage)
            $table->string('status_kelengkapan', 20)->default('kurang'); // lengkap / kurang
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'jenis']);
            $table->index(['tenant_id', 'status_kelengkapan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_mutu');
    }
};
