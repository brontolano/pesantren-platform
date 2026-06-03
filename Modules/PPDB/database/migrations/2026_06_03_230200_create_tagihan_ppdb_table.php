<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan_ppdb', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('calon_santri_id')->constrained('calon_santri')->cascadeOnDelete();
            $table->string('tipe', 50);
            $table->decimal('nominal', 15, 2);
            $table->date('jatuh_tempo');
            $table->string('status', 30)->default('draft');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'calon_santri_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'tipe']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan_ppdb');
    }
};
