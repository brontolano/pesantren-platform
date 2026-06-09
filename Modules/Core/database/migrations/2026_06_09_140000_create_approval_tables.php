<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1 — Approval Workflow Engine (lintas-modul, tenant-scoped).
 * approval_rule  = aturan kapan sebuah model butuh persetujuan + siapa penyetuju.
 * approval_request = instance permintaan persetujuan atas satu record (polymorphic).
 * Additif; modul consumer "menumpang" via trait Approvable (tidak bikin tabel sendiri).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_rule', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('approvable_type');           // FQCN model, mis. Modules\Pengadaan\Models\Belanja
            $table->string('nama');                       // label aturan
            $table->string('field')->nullable();          // field kondisi, mis. total
            $table->string('operator', 4)->default('>='); // > >= = <= <
            $table->decimal('nilai', 18, 2)->default(0);  // ambang
            $table->string('approver_role');              // role yang wajib menyetujui
            $table->string('mode', 12)->default('wajib');  // wajib | peringatan
            $table->unsignedSmallInteger('urutan')->default(1); // multi-step
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'approvable_type', 'aktif']);
        });

        Schema::create('approval_request', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->string('status', 12)->default('menunggu'); // menunggu | disetujui | ditolak
            $table->unsignedSmallInteger('current_step')->default(1);
            $table->string('approver_role')->nullable();
            $table->foreignId('diminta_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diputus_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diputus_pada')->nullable();
            $table->string('alasan')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'approvable_type', 'approvable_id']);
            $table->index(['tenant_id', 'status', 'approver_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_request');
        Schema::dropIfExists('approval_rule');
    }
};
