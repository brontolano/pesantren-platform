<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_wali', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('wali_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->string('relationship_type', 50)->default('orang_tua');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'wali_id', 'santri_id'], 'access_wali_tenant_wali_santri_unique');
            $table->index(['tenant_id', 'wali_id']);
            $table->index(['tenant_id', 'santri_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_wali');
    }
};
