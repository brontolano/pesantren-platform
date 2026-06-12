<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Pelengkap data keuangan demo agar seluruh modul terisi: skema biaya,
 * donasi, inventori kantin, rekonsiliasi bank, komponen gaji, pinjaman
 * pegawai, pembiayaan, beasiswa, setor keliling, jurnal sebulan, dan
 * tagihan bulan berjalan. Idempotent.
 */
class DemoKeuanganLengkapSeeder extends Seeder
{
    private string $tid;

    public function run(): void
    {
        $this->tid = tenant()->getTenantKey();

        $this->skemaBiaya();
        $this->anggaranDivisi();
        $this->donasi();
        $this->inventoriKantin();
        $this->rekonsiliasiBank();
        $this->komponenGaji();
        $this->pinjamanPegawai();
        $this->pembiayaan();
        $this->beasiswa();
        $this->setorKeliling();
        $this->jurnalSebulan();
        $this->tagihanBulanIni();

        $this->command?->info('Keuangan lengkap selesai.');
    }

    private function akunKas(): ?object
    {
        return DB::table('akun')->where('nama', 'ilike', '%kas%')->where('nama', 'not ilike', '%bank%')->where('is_postable', true)->first();
    }

    private function buatJurnal(string $tanggal, string $deskripsi, int $debitAkun, int $kreditAkun, float $nominal, string $key): void
    {
        if (DB::table('jurnal')->where('idempotency_key', $key)->exists()) {
            return;
        }

        $periodeId = DB::table('periode_akuntansi')->where('status', 'open')->value('id');
        $jurnalId = DB::table('jurnal')->insertGetId([
            'tenant_id' => $this->tid,
            'nomor' => 'JU-' . strtoupper(substr(md5($key), 0, 8)),
            'tanggal' => $tanggal,
            'deskripsi' => $deskripsi,
            'status' => 'posted',
            'idempotency_key' => $key,
            'periode_id' => $periodeId,
            'dibuat_oleh' => DB::table('users')->orderBy('id')->value('id'),
            'created_at' => $tanggal . ' 09:00:00',
            'updated_at' => now(),
        ]);

        DB::table('jurnal_baris')->insert([
            ['tenant_id' => $this->tid, 'jurnal_id' => $jurnalId, 'akun_id' => $debitAkun, 'debit' => $nominal, 'kredit' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $this->tid, 'jurnal_id' => $jurnalId, 'akun_id' => $kreditAkun, 'debit' => 0, 'kredit' => $nominal, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function skemaBiaya(): void
    {
        if (DB::table('skema_biaya')->count() > 0) {
            return;
        }

        $divisi = DB::table('divisi_unit')->where('is_active', true)->orderBy('id')->first();
        $dana = DB::table('sumber_dana')->where('is_active', true)->orderBy('id')->first();

        $rows = [
            ['jenis' => 'spp', 'nominal' => 500000, 'ket' => 'SPP bulanan santri mukim'],
            ['jenis' => 'makan', 'nominal' => 450000, 'ket' => 'Biaya makan 3x sehari'],
            ['jenis' => 'pendaftaran', 'nominal' => 750000, 'ket' => 'Biaya pendaftaran santri baru'],
            ['jenis' => 'daftar_ulang', 'nominal' => 350000, 'ket' => 'Daftar ulang tahunan'],
            ['jenis' => 'seragam', 'nominal' => 600000, 'ket' => 'Paket seragam 3 stel'],
            ['jenis' => 'kitab', 'nominal' => 400000, 'ket' => 'Paket kitab satu tahun'],
        ];

        foreach ($rows as $r) {
            DB::table('skema_biaya')->insert([
                'tenant_id' => $this->tid,
                'tahun_ajaran' => '2026/2027',
                'divisi_id' => $divisi?->id,
                'jenis_tagihan' => $r['jenis'],
                'nominal' => $r['nominal'],
                'sumber_dana_id' => $dana?->id,
                'keterangan' => $r['ket'],
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** Isi divisi_id + sumber_dana_id pada anggaran yang sudah ada, tambah anggaran per divisi. */
    private function anggaranDivisi(): void
    {
        $divisiIds = DB::table('divisi_unit')->where('is_active', true)->orderBy('id')->pluck('id')->toArray();
        $danaId = DB::table('sumber_dana')->where('is_active', true)->orderBy('id')->value('id');
        if (! $divisiIds) {
            return;
        }

        $kosong = DB::table('anggaran')->whereNull('divisi_id')->orderBy('id')->get();
        foreach ($kosong as $i => $a) {
            DB::table('anggaran')->where('id', $a->id)->update([
                'divisi_id' => $divisiIds[$i % count($divisiIds)],
                'sumber_dana_id' => $danaId,
                'updated_at' => now(),
            ]);
        }
    }

    private function donasi(): void
    {
        if (DB::table('program_donasi')->count() > 0) {
            return;
        }

        $danaSosial = DB::table('sumber_dana')->where('kategori', 'sosial')->value('id')
            ?? DB::table('sumber_dana')->orderBy('id')->value('id');
        $adminId = DB::table('users')->orderBy('id')->value('id');

        $programMasjid = DB::table('program_donasi')->insertGetId([
            'tenant_id' => $this->tid,
            'nama' => 'Pembangunan Masjid Jami',
            'deskripsi' => 'Perluasan masjid 2 lantai kapasitas 800 jamaah',
            'target_nominal' => 500000000,
            'sumber_dana_id' => $danaSosial,
            'aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $programYatim = DB::table('program_donasi')->insertGetId([
            'tenant_id' => $this->tid,
            'nama' => 'Beasiswa Santri Yatim & Dhuafa',
            'deskripsi' => 'Pembebasan SPP santri yatim dan dhuafa berprestasi',
            'target_nominal' => 100000000,
            'sumber_dana_id' => $danaSosial,
            'aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $donaturs = [
            ['program' => $programMasjid, 'nama' => 'H. Mahmud Abdullah', 'nominal' => 25000000, 'status' => 'dikonfirmasi', 'hari' => 9, 'urut' => 1],
            ['program' => $programMasjid, 'nama' => 'Hamba Allah', 'nominal' => 10000000, 'status' => 'dikonfirmasi', 'hari' => 7, 'urut' => 2],
            ['program' => $programYatim, 'nama' => 'Ibu Hj. Saodah', 'nominal' => 5000000, 'status' => 'dikonfirmasi', 'hari' => 5, 'urut' => 3],
            ['program' => $programMasjid, 'nama' => 'PT Berkah Tani Sejahtera', 'nominal' => 15000000, 'status' => 'dikonfirmasi', 'hari' => 3, 'urut' => 4],
            ['program' => $programYatim, 'nama' => 'Alumni Angkatan 2010', 'nominal' => 7500000, 'status' => 'menunggu_transfer', 'hari' => 1, 'urut' => null],
            ['program' => $programMasjid, 'nama' => 'Keluarga Bpk. Dadang', 'nominal' => 2000000, 'status' => 'menunggu_transfer', 'hari' => 0, 'urut' => null],
        ];

        foreach ($donaturs as $i => $d) {
            DB::table('donasi')->insert([
                'tenant_id' => $this->tid,
                'program_donasi_id' => $d['program'],
                'nama_donatur' => $d['nama'],
                'kontak' => '08120000' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'nominal' => $d['nominal'],
                'pesan' => 'Semoga berkah dan bermanfaat',
                'status' => $d['status'],
                'dikonfirmasi_oleh' => $d['status'] === 'dikonfirmasi' ? $adminId : null,
                'dikonfirmasi_at' => $d['status'] === 'dikonfirmasi' ? now()->subDays($d['hari']) : null,
                'nomor_kuitansi' => $d['urut'] ? 'KW-DON-2026-' . str_pad((string) $d['urut'], 4, '0', STR_PAD_LEFT) : null,
                'nomor_kuitansi_urut' => $d['urut'],
                'client_ref' => 'demo-donasi-' . $i,
                'created_at' => now()->subDays($d['hari']),
                'updated_at' => now(),
            ]);
        }
    }

    private function inventoriKantin(): void
    {
        if (DB::table('inventory_item')->count() > 0) {
            return;
        }

        $gudangId = DB::table('inventory_lokasi')->insertGetId([
            'tenant_id' => $this->tid, 'kode' => 'GDG-01', 'nama' => 'Gudang Utama',
            'tipe' => 'gudang', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $kantinId = DB::table('inventory_lokasi')->insertGetId([
            'tenant_id' => $this->tid, 'kode' => 'KTN-01', 'nama' => 'Kantin Santri',
            'tipe' => 'unit', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $items = [
            ['kode' => 'MKN-001', 'nama' => 'Nasi Goreng', 'satuan' => 'porsi', 'jual' => 9000, 'pokok' => 5500, 'stok' => 0],
            ['kode' => 'MKN-002', 'nama' => 'Mie Ayam', 'satuan' => 'porsi', 'jual' => 8000, 'pokok' => 5000, 'stok' => 0],
            ['kode' => 'MKN-003', 'nama' => 'Bakso Kuah', 'satuan' => 'porsi', 'jual' => 9000, 'pokok' => 6000, 'stok' => 0],
            ['kode' => 'MNM-001', 'nama' => 'Es Teh Manis', 'satuan' => 'gelas', 'jual' => 3000, 'pokok' => 1200, 'stok' => 0],
            ['kode' => 'MNM-002', 'nama' => 'Air Mineral 600ml', 'satuan' => 'botol', 'jual' => 3500, 'pokok' => 2300, 'stok' => 120],
            ['kode' => 'SNK-001', 'nama' => 'Roti Cokelat', 'satuan' => 'pcs', 'jual' => 5000, 'pokok' => 3500, 'stok' => 48],
            ['kode' => 'SNK-002', 'nama' => 'Biskuit Gandum', 'satuan' => 'pcs', 'jual' => 4000, 'pokok' => 2800, 'stok' => 60],
            ['kode' => 'SNK-003', 'nama' => 'Keripik Singkong', 'satuan' => 'bungkus', 'jual' => 3000, 'pokok' => 1800, 'stok' => 75],
            ['kode' => 'ATK-001', 'nama' => 'Buku Tulis 38 lbr', 'satuan' => 'pcs', 'jual' => 4500, 'pokok' => 3200, 'stok' => 200],
            ['kode' => 'ATK-002', 'nama' => 'Pulpen Standar', 'satuan' => 'pcs', 'jual' => 3000, 'pokok' => 1900, 'stok' => 150],
            ['kode' => 'ATK-003', 'nama' => 'Pensil 2B', 'satuan' => 'pcs', 'jual' => 2500, 'pokok' => 1500, 'stok' => 100],
            ['kode' => 'SBN-001', 'nama' => 'Sabun Mandi', 'satuan' => 'pcs', 'jual' => 5000, 'pokok' => 3700, 'stok' => 90],
        ];

        foreach ($items as $it) {
            $itemId = DB::table('inventory_item')->insertGetId([
                'tenant_id' => $this->tid,
                'kode' => $it['kode'],
                'nama' => $it['nama'],
                'satuan' => $it['satuan'],
                'harga_jual' => $it['jual'],
                'harga_pokok' => $it['pokok'],
                'stok' => $it['stok'],
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($it['stok'] > 0) {
                $diKantin = (int) floor($it['stok'] * 0.3);
                DB::table('inventory_stok_lokasi')->insert([
                    ['tenant_id' => $this->tid, 'item_id' => $itemId, 'lokasi_id' => $gudangId, 'stok' => $it['stok'] - $diKantin, 'created_at' => now(), 'updated_at' => now()],
                    ['tenant_id' => $this->tid, 'item_id' => $itemId, 'lokasi_id' => $kantinId, 'stok' => $diKantin, 'created_at' => now(), 'updated_at' => now()],
                ]);
            }
        }
    }

    private function rekonsiliasiBank(): void
    {
        if (DB::table('bank_statement')->count() > 0) {
            return;
        }

        $bank = DB::table('akun')->where('nama', 'ilike', '%bank%')->where('is_postable', true)->first();
        if (! $bank) {
            return;
        }

        $stmtId = DB::table('bank_statement')->insertGetId([
            'tenant_id' => $this->tid,
            'akun_id' => $bank->id,
            'nama' => 'Rekening BSI - Juni 2026',
            'periode_awal' => '2026-06-01',
            'periode_akhir' => '2026-06-30',
            'saldo_awal' => 0,
            'saldo_akhir' => 12400000,
            'sumber' => 'manual',
            'status' => 'draft',
            'diimpor_oleh' => DB::table('users')->orderBy('id')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lines = [
            ['tgl' => '2026-06-12', 'ket' => 'SETOR TUNAI ATM', 'ref' => 'BSI260612001', 'nominal' => 8000000, 'arah' => 'masuk'],
            ['tgl' => '2026-06-12', 'ket' => 'TRF MASUK SPP AN BUDI SANTOSO', 'ref' => 'BSI260612002', 'nominal' => 550000, 'arah' => 'masuk'],
            ['tgl' => '2026-06-12', 'ket' => 'TRF MASUK SPP AN AHMAD FAUZI', 'ref' => 'BSI260612003', 'nominal' => 550000, 'arah' => 'masuk'],
            ['tgl' => '2026-06-12', 'ket' => 'TRF MASUK SPP GABUNGAN 6 SANTRI', 'ref' => 'BSI260612004', 'nominal' => 3300000, 'arah' => 'masuk'],
            ['tgl' => '2026-06-13', 'ket' => 'BIAYA ADM BULANAN', 'ref' => 'BSI260613001', 'nominal' => 12500, 'arah' => 'keluar'],
        ];

        foreach ($lines as $l) {
            DB::table('bank_statement_line')->insert([
                'tenant_id' => $this->tid,
                'bank_statement_id' => $stmtId,
                'tanggal' => $l['tgl'],
                'keterangan' => $l['ket'],
                'referensi' => $l['ref'],
                'nominal' => $l['nominal'],
                'arah' => $l['arah'],
                'status' => 'belum_cocok',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function komponenGaji(): void
    {
        if (DB::table('komponen_gaji')->count() > 0) {
            return;
        }

        $employees = DB::table('employee')->where('aktif', true)->orderBy('id')->get();
        foreach ($employees as $i => $e) {
            $tetap = $e->status_kepegawaian === 'tetap';
            $rows = [
                ['nama' => 'Honor Pokok', 'tipe' => 'tetap', 'nominal' => $tetap ? 2500000 : 1800000],
                ['nama' => 'Tunjangan Jabatan', 'tipe' => 'tunjangan', 'nominal' => $tetap ? 500000 : 300000],
                ['nama' => 'Tunjangan Transport', 'tipe' => 'tunjangan', 'nominal' => 200000],
            ];
            if ($i % 2 === 0) {
                $rows[] = ['nama' => 'Iuran BPJS Kesehatan', 'tipe' => 'potongan', 'nominal' => 75000];
            }

            foreach ($rows as $r) {
                DB::table('komponen_gaji')->insert([
                    'tenant_id' => $this->tid,
                    'employee_id' => $e->id,
                    'nama' => $r['nama'],
                    'tipe' => $r['tipe'],
                    'nominal' => $r['nominal'],
                    'aktif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function pinjamanPegawai(): void
    {
        if (DB::table('pinjaman_pegawai')->count() > 0) {
            return;
        }

        $empIds = DB::table('employee')->where('aktif', true)->orderBy('id')->pluck('id')->toArray();
        $adminId = DB::table('users')->orderBy('id')->value('id');
        if (count($empIds) < 2) {
            return;
        }

        DB::table('pinjaman_pegawai')->insert([
            [
                'tenant_id' => $this->tid, 'employee_id' => $empIds[2] ?? $empIds[0],
                'nominal' => 2000000, 'tanggal' => '2026-04-05', 'cicilan_bulanan' => 200000,
                'sisa_pokok' => 1600000, 'status' => 'berjalan', 'disetujui_oleh' => $adminId,
                'catatan' => 'Pinjaman biaya berobat keluarga', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'tenant_id' => $this->tid, 'employee_id' => $empIds[4] ?? $empIds[1],
                'nominal' => 1000000, 'tanggal' => '2026-01-10', 'cicilan_bulanan' => 250000,
                'sisa_pokok' => 0, 'status' => 'lunas', 'disetujui_oleh' => $adminId,
                'catatan' => 'Pinjaman renovasi rumah - LUNAS', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'tenant_id' => $this->tid, 'employee_id' => $empIds[1],
                'nominal' => 3000000, 'tanggal' => now()->toDateString(), 'cicilan_bulanan' => 300000,
                'sisa_pokok' => 3000000, 'status' => 'diajukan',
                'catatan' => 'Pengajuan pinjaman biaya pendidikan anak', 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }

    private function pembiayaan(): void
    {
        if (DB::table('pengajuan_pembiayaan')->count() > 0) {
            return;
        }

        DB::table('pengajuan_pembiayaan')->insert([
            'tenant_id' => $this->tid,
            'bank_tujuan' => 'BSI Mitra Edu',
            'produk' => 'Pembiayaan Pembangunan Gedung Asrama',
            'plafon' => 750000000,
            'tenor' => 60,
            'margin' => 67500000,
            'ebitda_tahunan' => 285000000,
            'dscr' => 1.74,
            'zona' => 'aman',
            'status' => 'menunggu_pembina',
            'created_by' => DB::table('users')->orderBy('id')->value('id'),
            'created_at' => now()->subDays(4),
            'updated_at' => now(),
        ]);
    }

    private function beasiswa(): void
    {
        if (DB::table('beasiswa')->count() > 0) {
            return;
        }

        $danaSosial = DB::table('sumber_dana')->where('kategori', 'sosial')->value('id')
            ?? DB::table('sumber_dana')->orderBy('id')->value('id');
        $santriIds = DB::table('santri')->whereNull('deleted_at')->orderBy('id')->pluck('id')->toArray();
        if (! $danaSosial || count($santriIds) < 3) {
            return;
        }

        $rows = [
            ['sid' => $santriIds[2], 'nominal' => 500000, 'ket' => 'Beasiswa yatim - bebas SPP penuh'],
            ['sid' => $santriIds[8], 'nominal' => 250000, 'ket' => 'Beasiswa dhuafa - potongan 50% SPP'],
            ['sid' => $santriIds[14] ?? $santriIds[0], 'nominal' => 500000, 'ket' => 'Beasiswa tahfidz 15 juz'],
        ];

        foreach ($rows as $r) {
            DB::table('beasiswa')->insert([
                'tenant_id' => $this->tid,
                'santri_id' => $r['sid'],
                'sumber_dana_id' => $danaSosial,
                'nominal_per_bulan' => $r['nominal'],
                'aktif' => true,
                'mulai' => '2026-07-01',
                'keterangan' => $r['ket'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function setorKeliling(): void
    {
        if (DB::table('setor_keliling_sesi')->count() > 0) {
            return;
        }

        $petugasId = DB::table('users')->orderBy('id')->value('id');
        $santriIds = DB::table('santri')->whereNull('deleted_at')->orderBy('id')->limit(5)->pluck('id')->toArray();

        $sesiId = DB::table('setor_keliling_sesi')->insertGetId([
            'tenant_id' => $this->tid,
            'petugas_id' => $petugasId,
            'tanggal' => now()->toDateString(),
            'lokasi' => 'Asrama Blok A',
            'status' => 'diajukan',
            'total' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $total = 0;
        foreach ($santriIds as $i => $sid) {
            $nominal = [50000, 20000, 100000, 30000, 50000][$i];
            $total += $nominal;
            DB::table('setor_keliling_item')->insert([
                'tenant_id' => $this->tid,
                'sesi_id' => $sesiId,
                'santri_id' => $sid,
                'nominal' => $nominal,
                'catatan' => 'Titipan kiriman wali',
                'client_ref' => 'demo-sk-' . $sesiId . '-' . $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('setor_keliling_sesi')->where('id', $sesiId)->update(['total' => $total, 'updated_at' => now()]);
    }

    /** Jurnal harian sepanjang Juni agar BKU, laba-rugi & arus kas bermakna. */
    private function jurnalSebulan(): void
    {
        $kas = $this->akunKas();
        $pendapatan = DB::table('akun')->where('kode', 'like', '4%')->where('is_postable', true)->first();
        $beban = DB::table('akun')->where('kode', 'like', '5%')->where('is_postable', true)->first();
        if (! $kas || ! $pendapatan || ! $beban) {
            return;
        }

        $template = [
            ['d' => 2, 'desk' => 'Penerimaan SPP (12 santri)', 'arah' => 'masuk', 'n' => 6000000],
            ['d' => 2, 'desk' => 'Belanja dapur mingguan', 'arah' => 'keluar', 'n' => 3800000],
            ['d' => 4, 'desk' => 'Penjualan hasil kebun pesantren', 'arah' => 'masuk', 'n' => 1250000],
            ['d' => 4, 'desk' => 'Bayar langganan internet', 'arah' => 'keluar', 'n' => 750000],
            ['d' => 6, 'desk' => 'Infaq Jumat pekan ke-1', 'arah' => 'masuk', 'n' => 2150000],
            ['d' => 6, 'desk' => 'Servis kendaraan operasional', 'arah' => 'keluar', 'n' => 650000],
            ['d' => 9, 'desk' => 'Penerimaan daftar ulang (7 santri)', 'arah' => 'masuk', 'n' => 2450000],
            ['d' => 9, 'desk' => 'Belanja dapur mingguan', 'arah' => 'keluar', 'n' => 3950000],
            ['d' => 10, 'desk' => 'Pembelian gas LPG & beras', 'arah' => 'keluar', 'n' => 1870000],
            ['d' => 11, 'desk' => 'Penerimaan SPP (9 santri)', 'arah' => 'masuk', 'n' => 4500000],
            ['d' => 11, 'desk' => 'Honor pengajar tambahan tahsin', 'arah' => 'keluar', 'n' => 1200000],
        ];

        foreach ($template as $i => $t) {
            $tanggal = sprintf('2026-06-%02d', $t['d']);
            if ($t['arah'] === 'masuk') {
                $this->buatJurnal($tanggal, $t['desk'], $kas->id, $pendapatan->id, $t['n'], 'demo-juni-' . $i);
            } else {
                $this->buatJurnal($tanggal, $t['desk'], $beban->id, $kas->id, $t['n'], 'demo-juni-' . $i);
            }
        }
    }

    /** Tagihan SPP + makan bulan ini untuk semua santri aktif (variasi status). */
    private function tagihanBulanIni(): void
    {
        $bulan = now()->format('Y-m');
        if (DB::table('tagihan')->where('bulan_masehi', $bulan)->exists()) {
            return;
        }

        $adminId = DB::table('users')->orderBy('id')->value('id');
        $santri = DB::table('santri')->whereNull('deleted_at')->where('status', 'aktif')->orderBy('id')->get();

        foreach ($santri as $i => $s) {
            foreach ([['spp', 500000], ['makan', 450000]] as [$jenis, $nominal]) {
                DB::table('tagihan')->insert([
                    'tenant_id' => $this->tid,
                    'santri_id' => $s->id,
                    'jenis_tagihan' => $jenis,
                    'keterangan' => strtoupper($jenis) . ' ' . now()->translatedFormat('F Y'),
                    'nominal' => $nominal,
                    'diskon' => 0,
                    'nominal_akhir' => $nominal,
                    'jatuh_tempo' => now()->endOfMonth()->toDateString(),
                    'status' => $i % 3 === 0 ? 'lunas' : 'belum_bayar',
                    'periode_akademik' => '2026/2027',
                    'bulan_masehi' => $bulan,
                    'created_by' => $adminId,
                    'created_at' => now()->startOfMonth()->addDays(1),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
