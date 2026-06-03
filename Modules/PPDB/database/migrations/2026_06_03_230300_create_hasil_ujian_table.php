<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hasil_ujian', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('calon_santri_id')->constrained('calon_santri')->cascadeOnDelete();
            $table->string('kategori_ujian', 100);
            $table->decimal('nilai', 8, 2);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'calon_santri_id']);
            $table->index(['tenant_id', 'kategori_ujian']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_ujian');
    }
};
