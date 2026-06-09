<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2-14 — Inventory multi-lokasi + batch/lot (ADDITIF di atas inventory_item).
 *  - inventory_lokasi: gudang/unit/etalase.
 *  - inventory_stok_lokasi: stok per (item, lokasi). Total tetap di inventory_item.stok.
 *  - inventory_batch: lot + tanggal kedaluwarsa (FEFO).
 *  - kolom lokasi_id/batch_id pada inventory_mutasi (jejak per lokasi/lot).
 * Semua guarded hasTable/hasColumn → idempoten & aman re-run lintas tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_lokasi')) {
            Schema::create('inventory_lokasi', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('kode', 50);
                $table->string('nama');
                $table->string('tipe', 30)->default('gudang'); // gudang | unit | etalase
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'kode']);
                $table->index(['tenant_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('inventory_stok_lokasi')) {
            Schema::create('inventory_stok_lokasi', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->foreignId('item_id')->constrained('inventory_item')->cascadeOnDelete();
                $table->foreignId('lokasi_id')->constrained('inventory_lokasi')->cascadeOnDelete();
                $table->decimal('stok', 15, 3)->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'item_id', 'lokasi_id']);
                $table->index(['tenant_id', 'lokasi_id']);
            });
        }

        if (! Schema::hasTable('inventory_batch')) {
            Schema::create('inventory_batch', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->foreignId('item_id')->constrained('inventory_item')->cascadeOnDelete();
                $table->foreignId('lokasi_id')->nullable()->constrained('inventory_lokasi')->nullOnDelete();
                $table->string('nomor_batch', 80);
                $table->date('tanggal_kedaluwarsa')->nullable();
                $table->decimal('qty', 15, 3)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'item_id']);
                $table->index(['tenant_id', 'tanggal_kedaluwarsa']);
            });
        }

        if (Schema::hasTable('inventory_mutasi')) {
            Schema::table('inventory_mutasi', function (Blueprint $table) {
                if (! Schema::hasColumn('inventory_mutasi', 'lokasi_id')) {
                    $table->unsignedBigInteger('lokasi_id')->nullable()->after('item_id');
                }
                if (! Schema::hasColumn('inventory_mutasi', 'batch_id')) {
                    $table->unsignedBigInteger('batch_id')->nullable()->after('lokasi_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_mutasi')) {
            Schema::table('inventory_mutasi', function (Blueprint $table) {
                foreach (['lokasi_id', 'batch_id'] as $col) {
                    if (Schema::hasColumn('inventory_mutasi', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        Schema::dropIfExists('inventory_batch');
        Schema::dropIfExists('inventory_stok_lokasi');
        Schema::dropIfExists('inventory_lokasi');
    }
};
