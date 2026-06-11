<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-01 — Model Lembaga (Blueprint Akademik A-0 poin 1-2).
 *
 * (1) Tabel `lembaga`: payung formal/diniyah/tahfidz/pondok per tenant,
 *     tersambung opsional ke DivisiUnit Ledger (laporan keuangan per-unit).
 * (2) ALTER `kelas` + lembaga_id NULL — backfill data lama ke lembaga
 *     default "Pondok" (dibuat DI DALAM migrasi bila belum ada, per
 *     tenant_id yang muncul di kelas, agar migrasi per-DB tenant aman
 *     sebelum seeder TenantProvisioner berjalan; seeder firstOrCreate
 *     dengan kunci yang sama sehingga idempoten).
 * (3) ALTER `periode_akademik` + lembaga_id NULL + kalender + tahun_hijriyah;
 *     unique lama (tenant, tahun, semester) → dipecah dua partial unique
 *     index (lembaga NULL / NOT NULL) agar tenant TANPA lembaga di periode
 *     tetap terjaga seperti semula (unique biasa di Postgres tidak menjaga
 *     baris NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lembaga', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama');
            $table->string('jenis', 20)->default('pondok'); // formal|diniyah|tahfidz|pondok|mahad_aly|unit_usaha
            $table->string('jenjang', 50)->nullable();       // MI/MTs/MA/SMP/SMA/SMK | ula/wustha/ulya
            $table->string('nomor_statistik')->nullable();   // NSM/NPSN/NSPP
            $table->string('izin_operasional')->nullable();
            $table->date('tanggal_izin')->nullable();
            $table->foreignId('kepala_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('divisi_unit_id')->nullable()->constrained('divisi_unit')->nullOnDelete();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'aktif']);
            $table->unique(['tenant_id', 'nama']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE lembaga ADD CONSTRAINT lembaga_jenis_check CHECK (jenis IN ('formal','diniyah','tahfidz','pondok','mahad_aly','unit_usaha'))");
        }

        Schema::table('kelas', function (Blueprint $table): void {
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->nullOnDelete();
        });

        Schema::table('periode_akademik', function (Blueprint $table): void {
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->nullOnDelete();
            $table->string('kalender', 20)->default('masehi'); // masehi|hijriyah
            $table->string('tahun_hijriyah', 20)->nullable();  // LABEL manual, bukan konversi otomatis
        });

        // Backfill: kelas lama → lembaga default "Pondok" per tenant.
        // Lembaga default dibuat DI SINI bila belum ada (tenant lama yang
        // dimigrasi sebelum reprovision) — kunci sama dengan LembagaSeeder.
        $tenantIds = DB::table('kelas')->whereNull('lembaga_id')->distinct()->pluck('tenant_id');
        foreach ($tenantIds as $tenantId) {
            $lembagaId = DB::table('lembaga')
                ->where('tenant_id', $tenantId)
                ->where('nama', 'Pondok')
                ->value('id');

            if ($lembagaId === null) {
                $lembagaId = DB::table('lembaga')->insertGetId([
                    'tenant_id' => $tenantId,
                    'nama' => 'Pondok',
                    'jenis' => 'pondok',
                    'aktif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('kelas')
                ->where('tenant_id', $tenantId)
                ->whereNull('lembaga_id')
                ->update(['lembaga_id' => $lembagaId]);
        }

        // Unique periode: (tenant, tahun, semester) → per-lembaga.
        Schema::table('periode_akademik', function (Blueprint $table): void {
            $table->dropUnique('periode_akademik_tenant_tahun_semester_unique');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE periode_akademik ADD CONSTRAINT periode_akademik_kalender_check CHECK (kalender IN ('masehi','hijriyah'))");
            // Dua partial index: baris ber-lembaga unik per lembaga; baris
            // TANPA lembaga tetap unik per (tenant, tahun, semester) —
            // perilaku lama terjaga (NULL tidak dijaga unique biasa di PG).
            DB::statement('CREATE UNIQUE INDEX periode_akademik_tanpa_lembaga_unique ON periode_akademik (tenant_id, tahun_akademik, semester) WHERE lembaga_id IS NULL');
            DB::statement('CREATE UNIQUE INDEX periode_akademik_per_lembaga_unique ON periode_akademik (tenant_id, lembaga_id, tahun_akademik, semester) WHERE lembaga_id IS NOT NULL');
        } else {
            // Fallback non-pgsql (dev sqlite): unique komposit — NULL berbeda
            // dianggap distinct di sqlite, perlindungan baris NULL terbatas.
            Schema::table('periode_akademik', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'lembaga_id', 'tahun_akademik', 'semester'], 'periode_akademik_tenant_lembaga_tahun_semester_unique');
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS periode_akademik_tanpa_lembaga_unique');
            DB::statement('DROP INDEX IF EXISTS periode_akademik_per_lembaga_unique');
        } else {
            Schema::table('periode_akademik', function (Blueprint $table): void {
                $table->dropUnique('periode_akademik_tenant_lembaga_tahun_semester_unique');
            });
        }

        Schema::table('periode_akademik', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'tahun_akademik', 'semester'], 'periode_akademik_tenant_tahun_semester_unique');
            $table->dropConstrainedForeignId('lembaga_id');
            $table->dropColumn(['kalender', 'tahun_hijriyah']);
        });

        Schema::table('kelas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lembaga_id');
        });

        Schema::dropIfExists('lembaga');
    }
};
