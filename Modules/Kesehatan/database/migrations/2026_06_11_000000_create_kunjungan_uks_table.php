<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kunjungan_uks', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->date('tanggal');
            $table->time('jam_masuk');
            $table->time('jam_keluar')->nullable();
            $table->text('keluhan')->nullable();
            $table->text('tindakan')->nullable();
            $table->text('obat')->nullable();
            $table->enum('status', ['pulang', 'istirahat', 'rujuk'])->default('pulang');
            $table->string('rujukan_ke')->nullable();
            $table->decimal('rujukan_biaya', 12, 2)->nullable();
            $table->foreignId('pencatat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'santri_id', 'tanggal'], 'uq_kunjungan_uks_per_hari');
            $table->index(['tenant_id', 'santri_id']);
            $table->index(['tenant_id', 'tanggal']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kunjungan_uks');
    }
};
