<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_notifikasi', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('kode_template', 100);
            $table->string('judul');
            $table->text('isi');
            $table->json('variable_dinamis')->nullable();
            $table->string('channel', 20)->default('wa');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'kode_template']);
            $table->index(['tenant_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_notifikasi');
    }
};
