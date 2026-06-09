<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F5 — Document Repository (lintas-modul, tenant-scoped). Lampiran/dokumen
 * polymorphic + versioning. Modul consumer "menumpang" via trait HasDocuments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('documentable_type')->nullable(); // boleh standalone
            $table->unsignedBigInteger('documentable_id')->nullable();
            $table->string('kategori', 60)->nullable();        // ijazah, sertifikat, dokumen_halal, mou, dst
            $table->string('judul');
            $table->string('path');                            // disajikan via /_tenant-storage/{path}
            $table->string('nama_asli')->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('ukuran')->nullable();
            $table->unsignedInteger('versi')->default(1);
            $table->boolean('is_dokumen_halal')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'documentable_type', 'documentable_id']);
            $table->index(['tenant_id', 'kategori']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document');
    }
};
