<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2a Dasbor Mutu — tabel CACHE lampu kuning per santri (bukan data sumber;
 * sumber kebenaran tetap tabel modul masing-masing). Diisi ulang oleh job
 * harian `mutu:hitung-lampu`. Boleh di-truncate kapan pun tanpa kehilangan data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutu_lampu', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            // hafalan_macet | alpa | poin_disiplin | nilai_turun
            $table->string('jenis', 40);
            $table->string('label');
            $table->json('detail')->nullable();
            $table->timestamp('computed_at');
            // Guard anti-dobel notifikasi pada hari yang sama (idempoten harian).
            $table->date('last_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'santri_id', 'jenis']);
            $table->index(['tenant_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutu_lampu');
    }
};
