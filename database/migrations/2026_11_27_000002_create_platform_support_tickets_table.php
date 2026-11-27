<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_number', 32)->unique();
            $table->string('tenant_id')->index();
            $table->unsignedBigInteger('created_by_user_id')->index();
            $table->string('category', 40);
            $table->string('impact', 40)->default('sebagian_terhambat');
            $table->string('priority', 20)->default('normal');
            $table->string('subject', 180);
            $table->text('description');
            $table->string('status', 20)->default('open')->index();
            $table->unsignedBigInteger('status_updated_by')->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->timestamp('last_reply_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('platform_support_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_support_ticket_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('author_user_id')->index();
            $table->string('author_name', 160);
            $table->boolean('is_internal')->default(false)->index();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_support_replies');
        Schema::dropIfExists('platform_support_tickets');
    }
};
