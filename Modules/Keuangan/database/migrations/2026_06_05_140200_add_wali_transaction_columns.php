<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembayaran', function (Blueprint $table): void {
            if (! Schema::hasColumn('pembayaran', 'alasan_penolakan')) {
                $table->text('alasan_penolakan')->nullable()->after('catatan');
            }
        });

        Schema::table('tabungan', function (Blueprint $table): void {
            if (! Schema::hasColumn('tabungan', 'limit_harian')) {
                $table->decimal('limit_harian', 15, 2)->nullable()->after('saldo');
            }

            if (! Schema::hasColumn('tabungan', 'is_frozen')) {
                $table->boolean('is_frozen')->default(false)->after('limit_harian');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tabungan', function (Blueprint $table): void {
            if (Schema::hasColumn('tabungan', 'is_frozen')) {
                $table->dropColumn('is_frozen');
            }

            if (Schema::hasColumn('tabungan', 'limit_harian')) {
                $table->dropColumn('limit_harian');
            }
        });

        Schema::table('pembayaran', function (Blueprint $table): void {
            if (Schema::hasColumn('pembayaran', 'alasan_penolakan')) {
                $table->dropColumn('alasan_penolakan');
            }
        });
    }
};
