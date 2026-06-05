<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak audit aksi media (DB tenant): upload / hapus / pulihkan / kosongkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_audit', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('action'); // upload | hapus | pulihkan | kosongkan
            $table->unsignedBigInteger('media_file_id')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_audit');
    }
};
