<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * G3-19 — Template kegiatan, milestone/phase (Gantt), recurring (idempoten), dan
 * kolom penaut pada `kegiatan`. Additif & guarded. TIDAK mengubah Kegiatan/
 * PresensiKegiatan yang teruji (hanya menambah kolom nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kegiatan_template')) {
            Schema::create('kegiatan_template', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('nama');
                $table->text('deskripsi')->nullable();
                $table->string('jenis')->default('umum');
                $table->json('default_phases')->nullable(); // [{nama, offset_hari, durasi_hari}]
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('kegiatan_recurring')) {
            Schema::create('kegiatan_recurring', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('nama');
                $table->unsignedBigInteger('template_id')->nullable();
                $table->string('frekuensi', 12)->default('mingguan'); // mingguan | bulanan
                $table->unsignedInteger('interval')->default(1);       // tiap N minggu/bulan
                $table->date('mulai');                                  // anchor
                $table->date('akhir')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('kegiatan_phase')) {
            Schema::create('kegiatan_phase', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
                $table->string('nama');
                $table->unsignedInteger('urutan')->default(1);
                $table->date('tanggal_mulai')->nullable();
                $table->date('tanggal_selesai')->nullable();
                $table->string('status', 20)->default('rencana'); // rencana|berjalan|selesai
                $table->timestamps();

                $table->index(['tenant_id', 'kegiatan_id', 'urutan']);
            });
        }

        if (Schema::hasTable('kegiatan')) {
            Schema::table('kegiatan', function (Blueprint $table) {
                if (! Schema::hasColumn('kegiatan', 'template_id')) {
                    $table->unsignedBigInteger('template_id')->nullable()->after('jenis');
                }
                if (! Schema::hasColumn('kegiatan', 'recurring_id')) {
                    $table->unsignedBigInteger('recurring_id')->nullable()->after('template_id');
                }
                if (! Schema::hasColumn('kegiatan', 'tanggal_acuan')) {
                    $table->date('tanggal_acuan')->nullable()->after('recurring_id');
                }
            });

            // Idempotensi recurring: satu instance per (recurring, tanggal_acuan).
            // NULL recurring_id (kegiatan manual) dianggap distinct → tak terpengaruh.
            if (! $this->indexAda('kegiatan_recurring_unik')) {
                Schema::table('kegiatan', function (Blueprint $table) {
                    $table->unique(['tenant_id', 'recurring_id', 'tanggal_acuan'], 'kegiatan_recurring_unik');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('kegiatan') && $this->indexAda('kegiatan_recurring_unik')) {
            Schema::table('kegiatan', function (Blueprint $table) {
                $table->dropUnique('kegiatan_recurring_unik');
            });
        }
        if (Schema::hasTable('kegiatan')) {
            Schema::table('kegiatan', function (Blueprint $table) {
                foreach (['template_id', 'recurring_id', 'tanggal_acuan'] as $col) {
                    if (Schema::hasColumn('kegiatan', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        Schema::dropIfExists('kegiatan_phase');
        Schema::dropIfExists('kegiatan_recurring');
        Schema::dropIfExists('kegiatan_template');
    }

    private function indexAda(string $index): bool
    {
        try {
            return DB::table('pg_indexes')->where('indexname', $index)->exists();
        } catch (Throwable) {
            return false;
        }
    }
};
