<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Models\JurnalBaris;
use Modules\Ledger\Models\PeriodeAkuntansi;

/**
 * Seed data demo untuk PWA Kepala Keuangan.
 * Buat periode terbuka + jurnal agar Beranda tidak kosong.
 *
 * Jalankan: php artisan tenants:run db:seed --option="class=Database\Seeders\KepalaKeuanganDemoSeeder" --tenants=demo
 */
class KepalaKeuanganDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPeriode();
        $this->seedJurnalDemo();
    }

    private function seedPeriode(): void
    {
        PeriodeAkuntansi::firstOrCreate(
            ['nama' => 'Juni 2026'],
            [
                'tgl_mulai' => '2026-06-01',
                'tgl_selesai' => '2026-06-30',
                'status' => 'open',
            ]
        );
    }

    private function seedJurnalDemo(): void
    {
        if (Jurnal::where('status', 'posted')->exists()) {
            return;
        }

        $kas = Akun::where('is_kas_bank', true)->where('is_postable', true)->orderBy('kode')->first();
        if (! $kas) {
            return;
        }

        $pendapatan = Akun::where('kode', 'like', '4%')->where('is_postable', true)->first();
        $beban = Akun::where('kode', 'like', '5%')->where('is_postable', true)->first();

        if (! $pendapatan) {
            $pendapatan = Akun::firstOrCreate(
                ['kode' => '4-1000'],
                ['nama' => 'Pendapatan SPP', 'tipe' => 'pendapatan', 'is_postable' => true, 'is_kas_bank' => false, 'saldo_normal' => 'kredit']
            );
        }

        if (! $beban) {
            $beban = Akun::firstOrCreate(
                ['kode' => '5-1000'],
                ['nama' => 'Beban Operasional', 'tipe' => 'beban', 'is_postable' => true, 'is_kas_bank' => false, 'saldo_normal' => 'debit']
            );
        }

        $entries = [
            ['tanggal' => '2026-06-01', 'deskripsi' => 'Penerimaan SPP Bulan Juni (25 santri)', 'debit_akun' => $kas->id, 'kredit_akun' => $pendapatan->id, 'nominal' => 12500000],
            ['tanggal' => '2026-06-03', 'deskripsi' => 'Penerimaan Infaq Jumat', 'debit_akun' => $kas->id, 'kredit_akun' => $pendapatan->id, 'nominal' => 3200000],
            ['tanggal' => '2026-06-05', 'deskripsi' => 'Bayar listrik & air', 'debit_akun' => $beban->id, 'kredit_akun' => $kas->id, 'nominal' => 2800000],
            ['tanggal' => '2026-06-07', 'deskripsi' => 'Penerimaan SPP susulan (5 santri)', 'debit_akun' => $kas->id, 'kredit_akun' => $pendapatan->id, 'nominal' => 2500000],
            ['tanggal' => '2026-06-08', 'deskripsi' => 'Bayar honor asatidz', 'debit_akun' => $beban->id, 'kredit_akun' => $kas->id, 'nominal' => 8500000],
            ['tanggal' => '2026-06-10', 'deskripsi' => 'Penerimaan donasi pembangunan', 'debit_akun' => $kas->id, 'kredit_akun' => $pendapatan->id, 'nominal' => 5000000],
            ['tanggal' => now()->toDateString(), 'deskripsi' => 'Penerimaan SPP hari ini', 'debit_akun' => $kas->id, 'kredit_akun' => $pendapatan->id, 'nominal' => 1500000],
            ['tanggal' => now()->toDateString(), 'deskripsi' => 'Belanja ATK kantor', 'debit_akun' => $beban->id, 'kredit_akun' => $kas->id, 'nominal' => 450000],
        ];

        $counter = 1;
        foreach ($entries as $entry) {
            $jurnal = Jurnal::create([
                'nomor' => 'JU-DEMO-' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
                'tanggal' => $entry['tanggal'],
                'deskripsi' => $entry['deskripsi'],
                'status' => 'posted',
                'kehalalan' => 'halal',
                'idempotency_key' => 'demo-seed-' . $counter,
                'posted_at' => $entry['tanggal'],
                'created_by' => 1,
            ]);

            JurnalBaris::create([
                'jurnal_id' => $jurnal->id,
                'akun_id' => $entry['debit_akun'],
                'debit' => $entry['nominal'],
                'kredit' => 0,
            ]);

            JurnalBaris::create([
                'jurnal_id' => $jurnal->id,
                'akun_id' => $entry['kredit_akun'],
                'debit' => 0,
                'kredit' => $entry['nominal'],
            ]);

            $counter++;
        }
    }
}
