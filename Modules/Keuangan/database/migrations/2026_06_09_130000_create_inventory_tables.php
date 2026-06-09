<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory ringan Koperasi/Kantin (adaptasi Stock Ledger ERPNext) — item +
 * mutasi stok + valuasi average cost (harga_pokok). Additif, tenant-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_item', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('kode', 50);
            $table->string('nama');
            $table->string('satuan', 30)->default('pcs');
            $table->decimal('harga_jual', 15, 2)->default(0);
            $table->decimal('harga_pokok', 15, 2)->default(0); // rata-rata bergerak (avg cost)
            $table->decimal('stok', 15, 3)->default(0);
            $table->foreignId('akun_persediaan_id')->nullable()->constrained('akun')->nullOnDelete();
            $table->foreignId('akun_hpp_id')->nullable()->constrained('akun')->nullOnDelete();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'kode']);
            $table->index(['tenant_id', 'aktif']);
        });

        Schema::create('inventory_mutasi', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('item_id')->constrained('inventory_item')->cascadeOnDelete();
            $table->string('jenis', 20); // masuk | keluar | penyesuaian
            $table->decimal('qty', 15, 3);
            $table->decimal('harga_satuan', 15, 2)->default(0); // biaya per unit saat mutasi
            $table->decimal('stok_sesudah', 15, 3)->default(0);
            $table->string('ref_type')->nullable();
            $table->string('ref_id')->nullable();
            $table->string('keterangan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'item_id']);
            $table->index(['tenant_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_mutasi');
        Schema::dropIfExists('inventory_item');
    }
};
