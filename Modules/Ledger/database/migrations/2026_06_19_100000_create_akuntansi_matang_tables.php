<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G3-23 — Pematangan akuntansi: alokasi biaya (cost allocation per divisi) +
 * kurs valuta (multi-currency donor) + kolom valuta pada jurnal (record-only).
 * Additif & guarded. Posting tetap via JurnalService (IDR; math teruji tak diubah).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alokasi_biaya')) {
            Schema::create('alokasi_biaya', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('akun_id');           // beban yang dialokasikan
                $table->decimal('nominal', 18, 2);
                $table->json('driver');                          // [{divisi_id, bobot}]
                $table->string('periode', 20)->nullable();
                $table->string('keterangan')->nullable();
                $table->string('status', 20)->default('draft');  // draft|posted
                $table->unsignedBigInteger('jurnal_id')->nullable();
                $table->unsignedBigInteger('dibuat_oleh')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'akun_id']);
            });
        }

        if (! Schema::hasTable('kurs_valuta')) {
            Schema::create('kurs_valuta', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('mata_uang', 8);                  // USD|SAR|EUR|...
                $table->decimal('kurs', 18, 4);                  // 1 unit asing = Rp kurs
                $table->date('berlaku_tanggal');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                // UNIQUE: satu kurs per (tenant, mata uang, tanggal berlaku) → cegah
                // duplikat yang membuat kursTerkini() ambigu / konversi IDR non-deterministik.
                $table->unique(['tenant_id', 'mata_uang', 'berlaku_tanggal']);
            });
        }

        if (Schema::hasTable('jurnal')) {
            Schema::table('jurnal', function (Blueprint $table) {
                if (! Schema::hasColumn('jurnal', 'mata_uang')) {
                    $table->string('mata_uang', 8)->nullable()->after('tipe_sumber');
                }
                if (! Schema::hasColumn('jurnal', 'kurs')) {
                    $table->decimal('kurs', 18, 4)->nullable()->after('mata_uang');
                }
                if (! Schema::hasColumn('jurnal', 'nilai_asing')) {
                    $table->decimal('nilai_asing', 18, 2)->nullable()->after('kurs');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('jurnal')) {
            Schema::table('jurnal', function (Blueprint $table) {
                foreach (['mata_uang', 'kurs', 'nilai_asing'] as $col) {
                    if (Schema::hasColumn('jurnal', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        Schema::dropIfExists('kurs_valuta');
        Schema::dropIfExists('alokasi_biaya');
    }
};
