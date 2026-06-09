<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slip_gaji', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('users_id')->constrained('users')->cascadeOnDelete(); // PTK/asatidz
            $table->string('periode', 20);              // 2026-06
            $table->decimal('honor_pokok', 15, 2)->default(0);
            $table->decimal('tunjangan', 15, 2)->default(0);
            $table->integer('poin')->default(0);
            $table->decimal('tarif_poin', 15, 2)->default(0);
            $table->decimal('honor_poin', 15, 2)->default(0);   // poin × tarif (rutin berbasis kinerja)
            $table->decimal('honor_output', 15, 2)->default(0); // honor kegiatan/output (dipisah)
            $table->decimal('bruto', 15, 2)->default(0);
            $table->decimal('pph21_dipotong', 15, 2)->default(0);
            $table->decimal('neto', 15, 2)->default(0);
            $table->unsignedBigInteger('jurnal_id')->nullable();
            $table->string('status', 20)->default('draft'); // draft / final
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'users_id', 'periode']);
            $table->index(['tenant_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slip_gaji');
    }
};
