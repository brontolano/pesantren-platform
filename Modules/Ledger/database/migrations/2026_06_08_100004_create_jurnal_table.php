<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('nomor', 40);                      // JRN/2026/06/0001 (unik per tenant)
            $table->timestamp('tanggal');
            $table->string('deskripsi')->nullable();
            $table->string('tipe_sumber', 20)->default('manual'); // manual/syahriyah/belanja/payroll/jasa/pembalik
            // Idempotensi: satu transaksi sumber -> satu jurnal. (ref_type + ref_id) unik.
            $table->string('ref_type')->nullable();
            $table->string('ref_id')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->foreignId('periode_id')->nullable()->constrained('periode_akuntansi')->nullOnDelete();
            $table->string('status', 10)->default('posted');  // draft / posted / reversed
            // Jejak pembalik (immutability): jurnal pembalik menunjuk jurnal asal & sebaliknya.
            $table->foreignId('reversal_of_id')->nullable()->constrained('jurnal')->nullOnDelete();
            $table->foreignId('reversed_by_id')->nullable()->constrained('jurnal')->nullOnDelete();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'nomor']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'tanggal']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'ref_type', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal');
    }
};
