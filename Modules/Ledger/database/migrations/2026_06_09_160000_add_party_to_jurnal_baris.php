<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4 — Party Ledger: tag pihak (santri/supplier/employee/wali) pada baris jurnal
 * piutang/utang → buku besar pembantu per pihak + aging. Additif, nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal_baris', function (Blueprint $table) {
            $table->string('party_type')->nullable()->after('sumber_dana_id');
            $table->unsignedBigInteger('party_id')->nullable()->after('party_type');
            $table->index(['tenant_id', 'party_type', 'party_id'], 'jurnal_baris_party_idx');
        });
    }

    public function down(): void
    {
        Schema::table('jurnal_baris', function (Blueprint $table) {
            $table->dropIndex('jurnal_baris_party_idx');
            $table->dropColumn(['party_type', 'party_id']);
        });
    }
};
