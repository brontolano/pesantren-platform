<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_akuntansi', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama');                           // TA 2025/2026 — Semester Ganjil (Masehi/Hijriah)
            $table->date('tgl_mulai');
            $table->date('tgl_selesai');
            $table->string('status', 10)->default('open');    // open / closed
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'tgl_mulai', 'tgl_selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_akuntansi');
    }
};
