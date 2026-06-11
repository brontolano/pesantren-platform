<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('izin_santri', function (Blueprint $table) {
            $table->string('client_ref')->nullable()->after('alasan');
            $table->index(['tenant_id', 'client_ref']);
        });

        DB::statement('CREATE UNIQUE INDEX unique_client_ref_per_tenant ON izin_santri(tenant_id, client_ref) WHERE client_ref IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS unique_client_ref_per_tenant');
        Schema::table('izin_santri', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'client_ref']);
            $table->dropColumn('client_ref');
        });
    }
};
