<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama_kelas');           // e.g. "VII-A", "X IPA-1"
            $table->string('tingkat');               // e.g. "7", "10", "1 Ibtidaiyah"
            $table->string('jurusan')->nullable();   // e.g. "IPA", "IPS", null
            $table->unsignedInteger('capacity')->default(40);
            $table->foreignId('wali_kelas_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->unique(['tenant_id', 'nama_kelas']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
