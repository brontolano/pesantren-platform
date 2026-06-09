<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2-09 — Procurement chain: Supplier → Purchase Order (+items) → Goods Receipt
 * (qty_diterima, 3-way match) → Belanja (posting Ledger, dibuat saat terima penuh).
 * Additif & tenant-scoped. Approval lewat F1, budget lewat AnggaranGuardService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama');
            $table->string('npwp', 30)->nullable();
            $table->boolean('is_pkp')->default(false);          // pengusaha kena pajak → ber-NPWP
            $table->string('telepon', 30)->nullable();
            $table->string('alamat')->nullable();
            $table->string('bank_nama', 60)->nullable();
            $table->string('bank_rekening', 40)->nullable();
            $table->string('kehalalan', 12)->default('halal');  // halal/syubhat/non_halal
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'aktif']);
        });

        Schema::create('purchase_order', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('nomor', 40);
            $table->foreignId('supplier_id')->constrained('supplier')->restrictOnDelete();
            $table->date('tanggal');
            $table->foreignId('divisi_id')->nullable()->constrained('divisi_unit')->nullOnDelete();
            $table->foreignId('sumber_dana_id')->nullable()->constrained('sumber_dana')->nullOnDelete();
            $table->foreignId('akun_beban_id')->nullable()->constrained('akun')->nullOnDelete();
            $table->string('jenis', 10)->default('barang');     // barang/jasa/sewa
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 12)->default('draft');     // draft/diajukan/disetujui/diterima/ditutup/ditolak
            $table->string('keterangan')->nullable();
            $table->foreignId('belanja_id')->nullable()->constrained('belanja')->nullOnDelete(); // Belanja saat diterima
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_pada')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'nomor']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'supplier_id']);
        });

        Schema::create('purchase_order_item', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('purchase_order_id')->constrained('purchase_order')->cascadeOnDelete();
            $table->string('uraian');
            $table->decimal('qty', 12, 2)->default(1);
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('qty_diterima', 12, 2)->default(0); // GRN
            $table->timestamps();

            $table->index(['tenant_id', 'purchase_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_item');
        Schema::dropIfExists('purchase_order');
        Schema::dropIfExists('supplier');
    }
};
