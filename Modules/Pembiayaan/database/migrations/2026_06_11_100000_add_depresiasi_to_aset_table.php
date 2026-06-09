<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2-13 — Perluas `aset` untuk register aset tetap + penyusutan (ADDITIF).
 * Semua kolom guarded hasColumn agar idempoten & aman re-run lintas tenant.
 * Aset wakaf: metode_penyusutan = 'none' (UU 41/2004 — tidak disusutkan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aset')) {
            return;
        }

        Schema::table('aset', function (Blueprint $table) {
            if (! Schema::hasColumn('aset', 'tanggal_perolehan')) {
                $table->date('tanggal_perolehan')->nullable()->after('jenis');
            }
            if (! Schema::hasColumn('aset', 'nilai_perolehan')) {
                $table->decimal('nilai_perolehan', 18, 2)->default(0)->after('nilai');
            }
            if (! Schema::hasColumn('aset', 'lokasi')) {
                $table->string('lokasi')->nullable()->after('nilai_perolehan');
            }
            if (! Schema::hasColumn('aset', 'kategori')) {
                $table->string('kategori')->nullable()->after('lokasi'); // kelompok penyusutan
            }
            if (! Schema::hasColumn('aset', 'metode_penyusutan')) {
                // garis_lurus | saldo_menurun | none (wakaf/tanah)
                $table->string('metode_penyusutan')->default('none')->after('kategori');
            }
            if (! Schema::hasColumn('aset', 'nilai_residu')) {
                $table->decimal('nilai_residu', 18, 2)->default(0)->after('metode_penyusutan');
            }
            if (! Schema::hasColumn('aset', 'umur_manfaat_bulan')) {
                $table->unsignedInteger('umur_manfaat_bulan')->nullable()->after('nilai_residu');
            }
            if (! Schema::hasColumn('aset', 'akumulasi_penyusutan')) {
                $table->decimal('akumulasi_penyusutan', 18, 2)->default(0)->after('umur_manfaat_bulan');
            }
            if (! Schema::hasColumn('aset', 'status_aset')) {
                $table->string('status_aset')->default('aktif')->after('akumulasi_penyusutan'); // aktif|dilepas|dihapus
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('aset')) {
            return;
        }

        Schema::table('aset', function (Blueprint $table) {
            foreach ([
                'tanggal_perolehan', 'nilai_perolehan', 'lokasi', 'kategori', 'metode_penyusutan',
                'nilai_residu', 'umur_manfaat_bulan', 'akumulasi_penyusutan', 'status_aset',
            ] as $col) {
                if (Schema::hasColumn('aset', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
