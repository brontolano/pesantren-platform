<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Aktivitas "hari ini" agar demo terasa hidup: saldo bank, pembayaran masuk
 * (terverifikasi + antrean verifikasi), pengajuan belanja menunggu approval,
 * sesi POS terbuka dengan transaksi berjalan, dan jurnal hari ini.
 * Idempotent per hari — aman dijalankan ulang (dipakai juga via cron harian).
 */
class DemoAktivitasHarianSeeder extends Seeder
{
    private string $tid;

    public function run(): void
    {
        $this->tid = tenant()->getTenantKey();

        $this->saldoBank();
        $this->pembayaranHariIni();
        $this->pengajuanBelanja();
        $this->sesiPosAktif();
        $this->jurnalHariIni();

        $this->command?->info('Aktivitas harian demo selesai.');
    }

    private function akun(string $nama): ?object
    {
        return DB::table('akun')->where('nama', 'ilike', '%' . $nama . '%')->where('is_postable', true)->first();
    }

    private function buatJurnal(string $deskripsi, int $debitAkun, int $kreditAkun, float $nominal, string $key): void
    {
        if (DB::table('jurnal')->where('idempotency_key', $key)->exists()) {
            return;
        }

        $periodeId = DB::table('periode_akuntansi')->where('status', 'open')->value('id');

        $jurnalId = DB::table('jurnal')->insertGetId([
            'tenant_id' => $this->tid,
            'nomor' => 'JU-' . strtoupper(substr(md5($key), 0, 8)),
            'tanggal' => now()->toDateString(),
            'deskripsi' => $deskripsi,
            'status' => 'posted',
            'idempotency_key' => $key,
            'periode_id' => $periodeId,
            'dibuat_oleh' => DB::table('users')->orderBy('id')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('jurnal_baris')->insert([
            ['tenant_id' => $this->tid, 'jurnal_id' => $jurnalId, 'akun_id' => $debitAkun, 'debit' => $nominal, 'kredit' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $this->tid, 'jurnal_id' => $jurnalId, 'akun_id' => $kreditAkun, 'debit' => 0, 'kredit' => $nominal, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /** Transfer sebagian kas ke bank + penerimaan SPP via transfer bank. */
    private function saldoBank(): void
    {
        $kas = $this->akun('kas');
        $bank = $this->akun('bank');
        $pendapatan = DB::table('akun')->where('kode', 'like', '4%')->where('is_postable', true)->first();

        if (! $kas || ! $bank) {
            return;
        }

        $this->buatJurnal('Setor tunai ke rekening bank (BSI)', $bank->id, $kas->id, 8000000, 'demo-setor-bank-01');

        if ($pendapatan) {
            $this->buatJurnal('Penerimaan SPP via transfer bank (8 santri)', $bank->id, $pendapatan->id, 4400000, 'demo-spp-transfer-01');
        }
    }

    /** Pembayaran masuk hari ini: sebagian terverifikasi, sebagian antre. */
    private function pembayaranHariIni(): void
    {
        if (DB::table('pembayaran')->whereDate('created_at', now()->toDateString())->exists()) {
            return;
        }

        $adminId = DB::table('users')->orderBy('id')->value('id');
        $tagihan = DB::table('tagihan')
            ->where('status', 'belum_bayar')
            ->orderBy('id')
            ->limit(6)
            ->get();

        foreach ($tagihan as $i => $t) {
            $verified = $i < 2; // 2 sudah diverifikasi, 4 antre verifikasi
            DB::table('pembayaran')->insert([
                'tenant_id' => $this->tid,
                'tagihan_id' => $t->id,
                'santri_id' => $t->santri_id,
                'nominal' => $t->nominal_akhir,
                'metode' => $i % 2 === 0 ? 'transfer' : 'tunai',
                'tanggal_pembayaran' => now()->toDateString(),
                'nomor_bukti' => 'TRF-' . now()->format('Ymd') . '-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'catatan' => $i % 2 === 0 ? 'Transfer dari wali via m-banking' : 'Bayar tunai di loket',
                'is_verified' => $verified,
                'verified_by' => $verified ? $adminId : null,
                'verified_at' => $verified ? now()->subHours(2 - $i) : null,
                'created_by' => $adminId,
                'created_at' => now()->subHours(6 - $i),
                'updated_at' => now(),
            ]);

            if ($verified) {
                DB::table('tagihan')->where('id', $t->id)->update(['status' => 'lunas', 'updated_at' => now()]);
            }
        }
    }

    /** Pengajuan belanja menunggu persetujuan kepala keuangan. */
    private function pengajuanBelanja(): void
    {
        if (DB::table('approval_request')->where('status', 'menunggu')->exists()) {
            return;
        }

        $bendaharaId = DB::table('users')->orderBy('id')->skip(1)->value('id')
            ?? DB::table('users')->orderBy('id')->value('id');
        $beban = DB::table('akun')->where('kode', 'like', '5%')->where('is_postable', true)->first();
        $kas = $this->akun('kas');

        $pengajuan = [
            ['uraian' => 'Belanja beras & lauk dapur santri (1 minggu)', 'total' => 4250000],
            ['uraian' => 'Perbaikan pompa air asrama putra', 'total' => 1850000],
            ['uraian' => 'Pengadaan kitab kuning kelas takhassus', 'total' => 2700000],
        ];

        foreach ($pengajuan as $i => $p) {
            $belanjaId = DB::table('belanja')->insertGetId([
                'tenant_id' => $this->tid,
                'client_ref' => 'demo-belanja-' . now()->format('Ymd') . '-' . $i,
                'tanggal' => now()->toDateString(),
                'uraian' => $p['uraian'],
                'jenis' => 'barang',
                'dpp' => $p['total'],
                'vendor_npwp' => false,
                'ppn_dipungut' => 0,
                'pph_dipungut' => 0,
                'total' => $p['total'],
                'akun_beban_id' => $beban?->id,
                'akun_kas_id' => $kas?->id,
                'status_approval' => 'menunggu',
                'created_by' => $bendaharaId,
                'created_at' => now()->subHours(5 - $i),
                'updated_at' => now(),
            ]);

            DB::table('approval_request')->insert([
                'tenant_id' => $this->tid,
                'approvable_type' => 'Modules\\Pengadaan\\Models\\Belanja',
                'approvable_id' => $belanjaId,
                'status' => 'menunggu',
                'current_step' => 1,
                'approver_role' => 'kepala_keuangan',
                'diminta_oleh' => $bendaharaId,
                'created_at' => now()->subHours(5 - $i),
                'updated_at' => now(),
            ]);
        }
    }

    /** Sesi POS kantin terbuka hari ini dengan transaksi berjalan. */
    private function sesiPosAktif(): void
    {
        if (DB::table('pos_sesi')->where('status', 'open')->exists()) {
            return;
        }

        $kasirId = DB::table('users')->orderBy('id')->value('id');

        $sesiId = DB::table('pos_sesi')->insertGetId([
            'tenant_id' => $this->tid,
            'kasir_id' => $kasirId,
            'pos_jenis' => 'kantin',
            'saldo_awal' => 200000,
            'saldo_sistem' => 0,
            'status' => 'open',
            'dibuka_pada' => now()->startOfDay()->addHours(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menu = [
            ['ket' => 'Nasi kuning + telur', 'n' => 8000],
            ['ket' => 'Lontong sayur', 'n' => 7000],
            ['ket' => 'Es teh manis', 'n' => 3000],
            ['ket' => 'Gorengan 3 pcs', 'n' => 3000],
            ['ket' => 'Nasi goreng', 'n' => 9000],
            ['ket' => 'Air mineral', 'n' => 3000],
            ['ket' => 'Mie rebus telur', 'n' => 7000],
            ['ket' => 'Pisang goreng 2 pcs', 'n' => 4000],
        ];

        $omzet = 0;
        foreach ($menu as $i => $m) {
            $omzet += $m['n'];
            DB::table('pos_transaksi')->insert([
                'tenant_id' => $this->tid,
                'sesi_id' => $sesiId,
                'jenis' => 'penjualan',
                'nominal' => $m['n'],
                'keterangan' => $m['ket'],
                'dibuat_oleh' => $kasirId,
                'metode_bayar' => $i % 4 === 0 ? 'qris' : 'tunai',
                'harga_kotor' => $m['n'],
                'diskon' => 0,
                'poin' => 0,
                'created_at' => now()->subMinutes((count($menu) - $i) * 25),
                'updated_at' => now(),
            ]);
        }

        DB::table('pos_sesi')->where('id', $sesiId)->update(['saldo_sistem' => 200000 + $omzet, 'updated_at' => now()]);
    }

    /** Pengeluaran operasional hari ini agar ringkasan harian hidup. */
    private function jurnalHariIni(): void
    {
        $kas = $this->akun('kas');
        $beban = DB::table('akun')->where('kode', 'like', '5%')->where('is_postable', true)->first();
        $pendapatan = DB::table('akun')->where('kode', 'like', '4%')->where('is_postable', true)->first();

        if (! $kas || ! $beban) {
            return;
        }

        $hari = now()->format('Ymd');
        $this->buatJurnal('Belanja sayur & bumbu dapur harian', $beban->id, $kas->id, 850000, 'demo-harian-dapur-' . $hari);
        $this->buatJurnal('Transport undangan pengajian', $beban->id, $kas->id, 150000, 'demo-harian-transport-' . $hari);

        if ($pendapatan) {
            $this->buatJurnal('Infaq kotak masjid hari ini', $kas->id, $pendapatan->id, 670000, 'demo-harian-infaq-' . $hari);
        }
    }
}
