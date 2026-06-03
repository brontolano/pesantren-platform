<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_notifikasi', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('broadcast_id')->nullable()->constrained('broadcast_wa')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('template_notifikasi')->nullOnDelete();
            $table->string('jenis', 30);
            $table->string('channel', 20);
            $table->string('target');
            $table->string('status', 20);
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_notifikasi');
    }
};
