<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan_ppdb', function (Blueprint $table) {
            $table->foreignId('keuangan_tagihan_id')
                ->nullable()
                ->after('calon_santri_id')
                ->constrained('tagihan')
                ->nullOnDelete();

            $table->unique(['tenant_id', 'calon_santri_id', 'tipe'], 'tagihan_ppdb_tenant_candidate_type_unique');
            $table->index(['tenant_id', 'keuangan_tagihan_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tagihan_ppdb', function (Blueprint $table) {
            $table->dropUnique('tagihan_ppdb_tenant_candidate_type_unique');
            $table->dropIndex(['tenant_id', 'keuangan_tagihan_id']);
            $table->dropConstrainedForeignId('keuangan_tagihan_id');
        });
    }
};
