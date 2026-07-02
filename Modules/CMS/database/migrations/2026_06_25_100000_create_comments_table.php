<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komentar publik (1-aplikasi, Fase 2c) pada artikel CMS. Per-tenant (pola sama
 * dengan tabel `articles`): di-load via CMSServiceProvider::loadMigrationsFrom →
 * dijalankan di DB central & tiap DB tenant. Tenant EKSISTING butuh
 * `tenants:migrate --force` agar tabel ini muncul (pelajaran PPDB 500).
 *
 * Moderasi: status default 'pending' (pra-moderasi). Hanya 'approved' tampil
 * publik. Penulis = user login (jemaah/peran internal); nama disnapshot agar
 * tetap utuh bila akun dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comments')) {
            return;
        }

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name');
            $table->text('body');
            $table->string('status', 20)->default('pending');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'article_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
