<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisi_unit', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('kode', 30)->nullable();           // kode unit (opsional)
            $table->string('nama_divisi');                    // Madrasah Aliyah, Ma'had Aly, Unit Dapur, Sarpras
            $table->string('tipe_layanan', 30)->nullable();   // Formal / Non-Formal (klasifikasi EMIS)
            $table->boolean('is_komersial')->default(false);  // unit usaha (sumber EBITDA untuk DSCR)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
            $table->unique(['tenant_id', 'kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divisi_unit');
    }
};
