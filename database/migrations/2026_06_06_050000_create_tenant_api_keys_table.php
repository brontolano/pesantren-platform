<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API key per tenant (CENTRAL) — fondasi API publik headless.
 * Hanya HASH yang disimpan (sha256); kunci plaintext ditampilkan sekali saat dibuat.
 * Verifikasi: hash kunci yang dikirim → cari by key_hash → cocokkan tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('label')->nullable();
            $table->string('key_hash', 64)->unique(); // sha256 hex
            $table->string('prefix', 16);              // mis. psa_ab12cd34 (untuk identifikasi)
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_api_keys');
    }
};
