<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2 — Audit Trail (lintas-modul, tenant-scoped, APPEND-ONLY).
 * Jejak siapa-mengubah-apa-kapan untuk audit syariah & eksternal.
 * Modul consumer "menumpang" via trait Auditable (tak bikin tabel sendiri).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('aksi', 20);                 // create|update|delete|approve|post|reverse
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('perubahan')->nullable();       // {before:{}, after:{}}
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable(); // append-only, tanpa updated_at

            $table->index(['tenant_id', 'auditable_type', 'auditable_id']);
            $table->index(['tenant_id', 'actor_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
