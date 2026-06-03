<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calon_santri_dokumen', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('calon_santri_id')->constrained('calon_santri')->cascadeOnDelete();
            $table->string('jenis_dokumen', 100);
            $table->string('file_url');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'calon_santri_id']);
            $table->index(['tenant_id', 'jenis_dokumen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calon_santri_dokumen');
    }
};
