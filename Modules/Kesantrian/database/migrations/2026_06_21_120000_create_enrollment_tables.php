<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stasiun Pendaftaran Santri (Fase 0): kolom identitas/akses di santri +
 * tabel status enrollment per-langkah + sampel tulisan tangan berlabel
 * (dataset HTR — sistem membaca tulisan santri). Additif & guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('santri')) {
            Schema::table('santri', function (Blueprint $table): void {
                // Komponen mentah counter NIS (JANGAN parse balik string NIS).
                if (! Schema::hasColumn('santri', 'nis_urut')) {
                    $table->unsignedInteger('nis_urut')->nullable()->index();
                }
                // Kartu NFC: UID hex lowercase ternormalisasi. UID = identifikasi;
                // transaksi UANG wajib + PIN (MIFARE mudah dikloning).
                if (! Schema::hasColumn('santri', 'nfc_uid')) {
                    $table->string('nfc_uid', 64)->nullable();
                    $table->unique(['tenant_id', 'nfc_uid']);
                }
                // Tanda tangan: SVG master + PNG render (utk ID Card/dokumen).
                if (! Schema::hasColumn('santri', 'signature_svg_path')) {
                    $table->string('signature_svg_path')->nullable();
                }
                if (! Schema::hasColumn('santri', 'signature_path')) {
                    $table->string('signature_path')->nullable();
                }
                // Persetujuan kebijakan privasi sistem (UU PDP — data anak).
                if (! Schema::hasColumn('santri', 'consent_ai_dataset')) {
                    $table->boolean('consent_ai_dataset')->default(false);
                    $table->timestamp('consented_at')->nullable();
                    $table->string('consented_by')->nullable(); // nama wali penyetuju
                }
            });
        }

        if (! Schema::hasTable('santri_enrollments')) {
            Schema::create('santri_enrollments', function (Blueprint $table): void {
                $table->id();
                $table->string('tenant_id')->index();
                $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
                // Status kelengkapan EKSPLISIT per langkah (ala BIO_CAPTURED
                // Dukcapil) — bukan diturunkan dari keberadaan file.
                $table->string('step', 30); // foto|ttd|tulisan_arab|tulisan_latin|nfc|pin_qr|cetak_kartu|fingerprint
                $table->string('status', 15)->default('pending'); // pending|done|skipped
                $table->string('file_path')->nullable();
                $table->foreignId('captured_by')->nullable()->constrained('users');
                $table->timestamp('captured_at')->nullable();
                $table->timestamps();
                $table->unique(['santri_id', 'step']);
            });
        }

        if (! Schema::hasTable('handwriting_samples')) {
            Schema::create('handwriting_samples', function (Blueprint $table): void {
                $table->id();
                $table->string('tenant_id')->index();
                $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
                $table->string('script', 10); // arab|latin
                $table->string('form_type', 5)->default('A');
                $table->string('form_version', 20)->default('v1');
                $table->string('file_path');
                // Transkripsi PER BARIS — terisi otomatis (teks formulir
                // diketahui) → ground-truth utk HTR tanpa anotasi manual.
                $table->json('ground_truth')->nullable();
                $table->string('device', 20)->nullable(); // webcam|hp|scanner
                $table->string('status_qc', 10)->default('ok'); // ok|blur|ulang
                $table->timestamp('captured_at')->nullable();
                $table->timestamps();
                $table->index(['santri_id', 'script']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('handwriting_samples');
        Schema::dropIfExists('santri_enrollments');

        if (Schema::hasTable('santri')) {
            Schema::table('santri', function (Blueprint $table): void {
                foreach (['nis_urut', 'nfc_uid', 'signature_svg_path', 'signature_path', 'consent_ai_dataset', 'consented_at', 'consented_by'] as $col) {
                    if (Schema::hasColumn('santri', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
