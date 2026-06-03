<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_wa', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('template_id')->constrained('template_notifikasi')->cascadeOnDelete();
            $table->string('target_group', 50);
            $table->json('filter_criteria')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamp('tanggal_kirim')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->text('last_error_message')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_wa');
    }
};
