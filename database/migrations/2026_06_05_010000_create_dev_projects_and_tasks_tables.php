<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manajemen Proyek internal (CENTRAL only) — untuk memantau progres
 * pengembangan platform & modul. BUKAN data operasional tenant.
 * Hanya tampil di panel central (/platform) untuk super_admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('planning'); // planning | in_progress | on_hold | done
            $table->string('color')->default('gray');
            $table->string('icon')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('dev_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dev_project_id')->constrained('dev_projects')->cascadeOnDelete();
            $table->string('title');
            $table->text('detail')->nullable();          // apa yang dikerjakan / acceptance
            $table->text('current_state')->nullable();    // kondisi saat ini (gap note)
            $table->string('phase')->nullable();          // pengelompokan fase
            $table->string('status')->default('todo');    // todo | in_progress | blocked | done
            $table->string('priority')->default('medium');// low | medium | high | critical
            $table->unsignedTinyInteger('progress')->default(0); // 0-100
            $table->string('effort')->nullable();         // estimasi (S/M/L atau hari)
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_tasks');
        Schema::dropIfExists('dev_projects');
    }
};
