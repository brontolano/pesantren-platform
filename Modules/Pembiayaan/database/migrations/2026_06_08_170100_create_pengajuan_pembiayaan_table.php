<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_pembiayaan', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('bank_tujuan')->nullable();   // BSI Mitra Edu, Bank Mega Syariah
            $table->string('produk')->nullable();
            $table->decimal('plafon', 18, 2)->default(0);
            $table->integer('tenor')->default(0);        // bulan
            $table->decimal('margin', 18, 2)->default(0); // imbal hasil/margin tahunan
            $table->decimal('ebitda_tahunan', 18, 2)->default(0);
            $table->decimal('dscr', 8, 2)->nullable();   // hasil hitung (ambang 1,25)
            $table->string('zona', 10)->nullable();      // aman / waspada / bahaya
            $table->unsignedBigInteger('aset_agunan_id')->nullable(); // wajib non-wakaf
            $table->string('status', 30)->default('draft'); // draft/menunggu_pembina/disetujui/ditolak
            $table->string('esignature_pembina')->nullable(); // hash/URL TTE Pembina
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_pembiayaan');
    }
};
