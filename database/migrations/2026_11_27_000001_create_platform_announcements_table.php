<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('target_scope')->default('all');
            $table->string('tenant_id')->nullable()->index();
            $table->string('title', 180);
            $table->text('body');
            $table->string('category', 40)->default('informasi');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->unsignedBigInteger('published_by_user_id')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_announcement_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_announcement_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_id');
            $table->foreignId('user_id');
            $table->timestamp('read_at');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['platform_announcement_id', 'tenant_id', 'user_id'], 'platform_announcement_read_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_announcement_reads');
        Schema::dropIfExists('platform_announcements');
    }
};
