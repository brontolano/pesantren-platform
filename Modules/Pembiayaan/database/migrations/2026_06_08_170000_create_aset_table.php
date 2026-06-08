<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aset', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama_aset');
            $table->string('jenis')->nullable();        // tanah, bangunan, kendaraan, dll
            $table->decimal('nilai', 18, 2)->default(0); // nilai buku/taksiran
            $table->unsignedBigInteger('sumber_dana_id')->nullable();
            $table->boolean('is_wakaf')->default(false); // true → terkunci dari agunan (UU 41/2004 Ps 40)
            $table->string('nomor_sertifikat')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_wakaf']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aset');
    }
};
