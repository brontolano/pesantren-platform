<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G3-22 — Program formalization: program (kurikulum) + outcome/capaian
 * pembelajaran. Additif & guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('program_studi')) {
            Schema::create('program_studi', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('kode', 40)->nullable();
                $table->string('nama');
                $table->string('jenjang', 40)->nullable(); // MA/MTS/Mahad/dll
                $table->text('kurikulum')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('program_outcome')) {
            Schema::create('program_outcome', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->foreignId('program_id')->constrained('program_studi')->cascadeOnDelete();
                $table->string('kode', 40)->nullable();
                $table->string('deskripsi');
                $table->unsignedInteger('urutan')->default(1);
                $table->timestamps();

                $table->index(['tenant_id', 'program_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('program_outcome');
        Schema::dropIfExists('program_studi');
    }
};
