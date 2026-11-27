<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_runtime_heartbeats', function (Blueprint $table): void {
            $table->string('service_key', 80)->primary();
            $table->timestamp('last_seen_at')->index();
            $table->string('detail', 180)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_runtime_heartbeats');
    }
};
