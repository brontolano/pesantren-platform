<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calon_santri', function (Blueprint $table) {
            $table->text('catatan_keputusan')->nullable()->after('status_pendaftaran');
            $table->timestamp('tanggal_keputusan')->nullable()->after('catatan_keputusan');
        });
    }

    public function down(): void
    {
        Schema::table('calon_santri', function (Blueprint $table) {
            $table->dropColumn([
                'catatan_keputusan',
                'tanggal_keputusan',
            ]);
        });
    }
};
