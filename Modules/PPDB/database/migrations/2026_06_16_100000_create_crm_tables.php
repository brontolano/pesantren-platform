<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G3-20 — CRM ringan: penawaran (quotation dari SkemaBiaya) + aktivitas CRM
 * (campaign re-enrollment / kontak / catatan, dengan idempotensi via `ref`).
 * Additif & guarded. Tidak menyentuh CalonSantri/Tagihan/SkemaBiaya teruji.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_penawaran')) {
            Schema::create('crm_penawaran', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('calon_santri_id')->nullable();
                $table->unsignedBigInteger('santri_id')->nullable();
                $table->string('nama_prospek')->nullable();
                $table->string('tahun_ajaran', 20)->nullable();
                $table->json('items')->nullable();           // [{jenis, nominal}]
                $table->decimal('total', 15, 2)->default(0);
                $table->string('status', 20)->default('draft'); // draft|terkirim
                $table->string('keterangan')->nullable();
                $table->unsignedBigInteger('dibuat_oleh')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'tahun_ajaran']);
            });
        }

        if (! Schema::hasTable('crm_aktivitas')) {
            Schema::create('crm_aktivitas', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('tipe', 20)->default('catatan'); // campaign|kontak|catatan
                $table->unsignedBigInteger('calon_santri_id')->nullable();
                $table->unsignedBigInteger('santri_id')->nullable();
                $table->string('kanal', 20)->nullable();         // wa|email|log
                $table->string('ref')->nullable();               // kunci idempotensi campaign
                $table->string('status', 20)->default('terkirim');
                $table->string('keterangan')->nullable();
                $table->timestamp('dikirim_pada')->nullable();
                $table->unsignedBigInteger('dibuat_oleh')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'tipe']);
                // Idempotensi campaign: satu campaign per (calon, ref).
                $table->unique(['tenant_id', 'calon_santri_id', 'ref'], 'crm_aktivitas_campaign_unik');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_aktivitas');
        Schema::dropIfExists('crm_penawaran');
    }
};
