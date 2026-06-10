<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-A002 — ai_tenant_budget: anggaran kredit AI bulanan + kill-switch per
 * tenant. Tabel CENTRAL (operator yang mengelola anggaran/billing).
 *
 * Baris ini juga berfungsi sebagai TITIK KUNCI (SELECT ... FOR UPDATE) untuk
 * serialisasi penulisan ledger per tenant di CreditMeter::catat().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tenant_budget', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->unique();
            // NULL = tanpa batas anggaran (default tenant baru).
            $table->decimal('anggaran_kredit_bulanan', 14, 4)->nullable();
            $table->unsignedSmallInteger('ambang_alert_persen')->default(80);
            // Awal bulan periode berjalan; rollover di CreditMeter saat bulan berganti.
            $table->date('periode_berjalan');
            $table->decimal('terpakai_periode', 14, 4)->default(0);
            $table->boolean('kill_switch_aktif')->default(false);
            $table->timestamp('alert_terkirim_pada')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tenant_budget');
    }
};
