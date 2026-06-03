<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calon_santri', function (Blueprint $table) {
            $table->foreignId('santri_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('santri')
                ->nullOnDelete();
            $table->timestamp('tanggal_registrasi_ulang')
                ->nullable()
                ->after('tanggal_keputusan');
        });
    }

    public function down(): void
    {
        Schema::table('calon_santri', function (Blueprint $table) {
            $table->dropConstrainedForeignId('santri_id');
            $table->dropColumn('tanggal_registrasi_ulang');
        });
    }
};
