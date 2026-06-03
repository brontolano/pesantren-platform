<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calon_santri', function (Blueprint $table) {
            $table->string('tracking_token', 64)->nullable()->after('nomor_pendaftaran');
            $table->timestamp('tracking_token_generated_at')->nullable()->after('tracking_token');

            $table->unique(['tenant_id', 'tracking_token']);
        });
    }

    public function down(): void
    {
        Schema::table('calon_santri', function (Blueprint $table) {
            $table->dropUnique('calon_santri_tenant_id_tracking_token_unique');
            $table->dropColumn(['tracking_token', 'tracking_token_generated_at']);
        });
    }
};
