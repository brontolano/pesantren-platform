<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('belanja', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('client_ref')->nullable(); // idempotency key dari PWA (offline-sync)
            $table->date('tanggal');
            $table->string('uraian');
            $table->string('jenis', 20);                 // barang / jasa / sewa
            $table->string('kategori_rkam')->nullable(); // klasifikasi RKAM/RAB
            // Dimensi (tanpa FK keras agar loose-coupling dgn modul Ledger)
            $table->unsignedBigInteger('divisi_id')->nullable();
            $table->unsignedBigInteger('sumber_dana_id')->nullable();
            $table->decimal('dpp', 15, 2);               // dasar pengenaan pajak (sebelum pajak)
            $table->boolean('vendor_npwp')->default(false);
            // Hasil mesin pajak
            $table->decimal('ppn_dipungut', 15, 2)->default(0);
            $table->string('pph_jenis', 20)->nullable(); // pph22 / pph23 / pph_final_42
            $table->decimal('pph_dipungut', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0); // dpp + ppn (nilai yang dibayar/dibukukan)
            // Akun pembukuan (beban/aset yang didebit; kas/utang yang dikredit)
            $table->unsignedBigInteger('akun_beban_id')->nullable();
            $table->unsignedBigInteger('akun_kas_id')->nullable();
            $table->unsignedBigInteger('jurnal_id')->nullable(); // 1:1 ke jurnal hasil posting
            $table->string('status_approval', 20)->default('diajukan'); // diajukan/disetujui/ditolak/dicairkan
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status_approval']);
            $table->index(['tenant_id', 'tanggal']);
            $table->unique(['tenant_id', 'client_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('belanja');
    }
};
