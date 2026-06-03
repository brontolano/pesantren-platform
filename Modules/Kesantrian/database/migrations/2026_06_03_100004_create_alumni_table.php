<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumni', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->string('tahun_lulus', 4);
            $table->string('status_lanjutan')->nullable()->comment('e.g. kuliah, kerja, pesantren lanjutan');
            $table->string('sekolah_tujuan')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->unique(['tenant_id', 'santri_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni');
    }
};
