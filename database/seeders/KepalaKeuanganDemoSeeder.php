<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Models\JurnalBaris;
use Modules\Ledger\Models\PeriodeAkuntansi;

class KepalaKeuanganDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPeriode();
        $this->seedJurnalDemo();
        $this->seedAnggaran();
        $this->seedPosTransaksi();
        $this->seedSlipGaji();
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
        if (Jurnal::where('status', 'posted')->count() >= 8) {
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

        $counter = Jurnal::max('id') + 1;
        foreach ($entries as $entry) {
            $jurnal = Jurnal::create([
                'nomor' => 'JU-DEMO-' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
                'tanggal' => $entry['tanggal'],
                'deskripsi' => $entry['deskripsi'],
                'status' => 'posted',
                'kehalalan' => $counter % 5 === 0 ? 'syubhat' : 'halal',
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

    private function seedAnggaran(): void
    {
        if (DB::table('anggaran')->count() > 0) {
            return;
        }

        $bebanAkun = Akun::where('kode', 'like', '5%')->where('is_postable', true)->first();
        if (! $bebanAkun) {
            return;
        }

        $anggaranData = [
            ['nominal' => 35000000, 'ket' => 'Anggaran operasional pendidikan'],
            ['nominal' => 25000000, 'ket' => 'Pemeliharaan gedung & inventaris'],
            ['nominal' => 15000000, 'ket' => 'Kegiatan santri & ekstrakurikuler'],
            ['nominal' => 10000000, 'ket' => 'Dakwah, humas & publikasi'],
        ];

        foreach ($anggaranData as $a) {
            DB::table('anggaran')->insert([
                'tenant_id' => tenant()->getTenantKey(),
                'tahun_anggaran' => '2026',
                'akun_id' => $bebanAkun->id,
                'divisi_id' => null,
                'sumber_dana_id' => null,
                'nominal_anggaran' => $a['nominal'],
                'mode' => 'peringatan',
                'keterangan' => $a['ket'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedPosTransaksi(): void
    {
        if (DB::table('pos_transaksi')->count() > 0) {
            return;
        }

        $sesi = DB::table('pos_sesi')->where('status', 'closed')->first()
            ?? DB::table('pos_sesi')->first();

        if (! $sesi) {
            return;
        }

        $items = [
            ['ket' => 'Nasi goreng', 'nominal' => 8000, 'jenis' => 'penjualan'],
            ['ket' => 'Mie ayam', 'nominal' => 7000, 'jenis' => 'penjualan'],
            ['ket' => 'Es teh manis', 'nominal' => 3000, 'jenis' => 'penjualan'],
            ['ket' => 'Roti bakar', 'nominal' => 5000, 'jenis' => 'penjualan'],
            ['ket' => 'Aqua botol', 'nominal' => 3000, 'jenis' => 'penjualan'],
            ['ket' => 'Nasi ayam geprek', 'nominal' => 10000, 'jenis' => 'penjualan'],
            ['ket' => 'Jus jeruk', 'nominal' => 5000, 'jenis' => 'penjualan'],
            ['ket' => 'Bakso', 'nominal' => 8000, 'jenis' => 'penjualan'],
            ['ket' => 'Sate ayam', 'nominal' => 12000, 'jenis' => 'penjualan'],
            ['ket' => 'Nasi uduk', 'nominal' => 6000, 'jenis' => 'penjualan'],
            ['ket' => 'Gorengan 5 pcs', 'nominal' => 5000, 'jenis' => 'penjualan'],
            ['ket' => 'Tahu crispy', 'nominal' => 4000, 'jenis' => 'penjualan'],
            ['ket' => 'Es campur', 'nominal' => 7000, 'jenis' => 'penjualan'],
            ['ket' => 'Nasi goreng spesial', 'nominal' => 12000, 'jenis' => 'penjualan'],
            ['ket' => 'Indomie telur', 'nominal' => 6000, 'jenis' => 'penjualan'],
        ];

        foreach ($items as $i => $item) {
            DB::table('pos_transaksi')->insert([
                'tenant_id' => tenant()->getTenantKey(),
                'sesi_id' => $sesi->id,
                'kategori_id' => null,
                'jenis' => $item['jenis'],
                'nominal' => $item['nominal'],
                'santri_id' => null,
                'ref_type' => null,
                'ref_id' => null,
                'jurnal_id' => null,
                'keterangan' => $item['ket'],
                'dibuat_oleh' => $sesi->kasir_id ?? 1,
                'metode_bayar' => $i % 3 === 0 ? 'qris' : 'tunai',
                'harga_kotor' => $item['nominal'],
                'diskon' => 0,
                'poin' => 0,
                'created_at' => now()->subHours(rand(1, 8))->subMinutes(rand(0, 59)),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedSlipGaji(): void
    {
        if (DB::table('slip_gaji')->count() > 0) {
            return;
        }

        $userIds = DB::table('users')->pluck('id')->take(6)->toArray();
        if (empty($userIds)) {
            return;
        }

        $names = ['Ustadz Ahmad', 'Ustadzah Fatimah', 'Ustadz Ridwan', 'Pak Hasan (TU)', 'Bu Aisyah (Dapur)', 'Pak Mahmud (Keamanan)'];

        foreach ($userIds as $i => $uid) {
            $honorPokok = match (true) {
                $i < 3 => 2500000,
                default => 1800000,
            };
            $tunjangan = $i < 3 ? 500000 : 300000;
            $bruto = $honorPokok + $tunjangan;
            $pph = $bruto > 2500000 ? (int) ($bruto * 0.025) : 0;
            $neto = $bruto - $pph;

            DB::table('slip_gaji')->insert([
                'tenant_id' => tenant()->getTenantKey(),
                'users_id' => $uid,
                'periode' => '2026-06',
                'honor_pokok' => $honorPokok,
                'tunjangan' => $tunjangan,
                'poin' => 0,
                'tarif_poin' => 0,
                'honor_poin' => 0,
                'honor_output' => 0,
                'bruto' => $bruto,
                'pph21_dipotong' => $pph,
                'neto' => $neto,
                'jurnal_id' => null,
                'status' => $i < 4 ? 'final' : 'draft',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'bpjs_dipotong' => 0,
                'potongan_cuti' => 0,
                'tipe' => 'gaji',
                'tahun_thr' => null,
                'potongan_kasbon' => $i === 2 ? 200000 : 0,
            ]);
        }
    }
}
