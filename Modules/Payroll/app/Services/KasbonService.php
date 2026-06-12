<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Services\ApprovalService;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Services\JurnalService;
use Modules\Payroll\Models\PinjamanPegawai;
use RuntimeException;

/**
 * WP-23 — Kasbon / pinjaman pegawai (MONEY WP).
 *
 * - Approval lewat F1 (trait Approvable di PinjamanPegawai — pola Cuti persis).
 * - Pencairan: D Piutang Pegawai (1-1400) / K Kas, idempoten via ref
 *   (PinjamanPegawai, id) — non-null; pencairan 2× = 1 jurnal (status guard
 *   conditional update + lockForUpdate + dedup JurnalService).
 * - Potongan cicilan bulanan dipotong PayrollService::terapkanPotongan();
 *   sisa_pokok berkurang HANYA saat slip FINAL (lihat kurangiSisaSaatFinal()).
 * - Pelunasan dipercepat: D Kas / K Piutang Pegawai, kunci idempotensi
 *   deterministik per (pinjaman, sisa sebelum bayar) — sisa monoton turun
 *   sehingga submit ganda nominal sama tak menggandakan jurnal.
 */
class KasbonService
{
    public const KODE_AKUN_PIUTANG_PEGAWAI = '1-1400';

    public function __construct(private readonly JurnalService $jurnalService) {}

    /** Ajukan kasbon: validasi nominal/cicilan + status diajukan + F1 ajukanPersetujuan. */
    public function ajukan(int $employeeId, float $nominal, float $cicilanBulanan, ?string $tanggal = null, ?string $catatan = null, ?int $userId = null): PinjamanPegawai
    {
        $this->ensureTenantContext();

        $nominal = round($nominal, 2);
        $cicilanBulanan = round($cicilanBulanan, 2);
        if ($nominal <= 0) {
            throw new InvalidArgumentException('Nominal kasbon harus lebih besar dari 0.');
        }
        if ($cicilanBulanan <= 0) {
            throw new InvalidArgumentException('Cicilan bulanan harus lebih besar dari 0.');
        }
        if ($cicilanBulanan > $nominal) {
            // Boleh lunas sekali potong, tapi cicilan > pokok hampir pasti salah input.
            throw new InvalidArgumentException('Cicilan bulanan tidak boleh melebihi nominal pinjaman.');
        }

        $pinjaman = PinjamanPegawai::create([
            'employee_id' => $employeeId,
            'nominal' => $nominal,
            'tanggal' => Carbon::parse($tanggal ?? now())->toDateString(),
            'cicilan_bulanan' => $cicilanBulanan,
            'sisa_pokok' => $nominal,
            'status' => 'diajukan',
            'catatan' => $catatan,
        ]);

        $pinjaman->ajukanPersetujuan($userId); // F1: buat request bila ada rule wajib

        return $pinjaman->fresh();
    }

    /** Setujui: pagar F1 (no-op tanpa rule) → status disetujui (conditional update, pola Cuti). */
    public function setujui(PinjamanPegawai $pinjaman, ?int $userId = null): PinjamanPegawai
    {
        $this->ensureTenantContext();
        if ($pinjaman->status !== 'diajukan') {
            throw new InvalidArgumentException('Hanya kasbon berstatus diajukan yang dapat disetujui.');
        }

        $pinjaman->pastikanBolehLanjut();

        if ($req = $pinjaman->approvalRequests()->where('status', 'menunggu')->latest('id')->first()) {
            app(ApprovalService::class)->setujui($req, (int) ($userId ?? 0));
        }

        $diubah = PinjamanPegawai::query()->whereKey($pinjaman->id)->where('status', 'diajukan')
            ->update(['status' => 'disetujui', 'disetujui_oleh' => $userId]);
        if ($diubah === 0) {
            throw new InvalidArgumentException('Status kasbon sudah berubah — muat ulang.');
        }

        return $pinjaman->fresh();
    }

    /** Tolak / batalkan pengajuan (hanya sebelum pencairan). */
    public function batalkan(PinjamanPegawai $pinjaman, ?int $userId = null, ?string $alasan = null): PinjamanPegawai
    {
        $this->ensureTenantContext();
        if (! in_array($pinjaman->status, ['diajukan', 'disetujui'], true)) {
            throw new InvalidArgumentException('Kasbon yang sudah dicairkan tidak dapat dibatalkan.');
        }

        if ($req = $pinjaman->approvalRequests()->where('status', 'menunggu')->latest('id')->first()) {
            app(ApprovalService::class)->tolak($req, (int) ($userId ?? 0), $alasan ?? 'Dibatalkan');
        }

        $diubah = PinjamanPegawai::query()->whereKey($pinjaman->id)
            ->whereIn('status', ['diajukan', 'disetujui'])->update(['status' => 'batal']);
        if ($diubah === 0) {
            throw new InvalidArgumentException('Status kasbon sudah berubah — muat ulang.');
        }

        return $pinjaman->fresh();
    }

    /**
     * Pencairan: HANYA status disetujui + pagar F1. Posting D Piutang Pegawai /
     * K Kas, ref (PinjamanPegawai, id) non-null → idempoten; lock + conditional
     * update mencegah dua pencairan paralel.
     */
    public function cairkan(PinjamanPegawai $pinjaman, ?int $userId = null): PinjamanPegawai
    {
        $this->ensureTenantContext();

        if ($pinjaman->status === 'berjalan' && $pinjaman->jurnal_pencairan_id) {
            return $pinjaman; // sudah cair — idempoten.
        }
        if ($pinjaman->status !== 'disetujui') {
            throw new InvalidArgumentException('Hanya kasbon berstatus disetujui yang dapat dicairkan.');
        }

        // Pagar F1 sebelum posting uang (pola finalisasi SlipGaji).
        $pinjaman->pastikanBolehLanjut();

        $piutang = $this->akunPiutangPegawai();
        $kas = Akun::kasBank()->where('is_postable', true)->orderBy('kode')->first();
        if ($kas === null) {
            throw new RuntimeException('Akun kas belum tersedia (COA belum di-seed).');
        }

        return DB::transaction(function () use ($pinjaman, $userId, $piutang, $kas): PinjamanPegawai {
            $locked = PinjamanPegawai::query()->whereKey($pinjaman->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'berjalan' && $locked->jurnal_pencairan_id) {
                return $locked; // balapan: pencairan lain menang lebih dulu.
            }
            if ($locked->status !== 'disetujui') {
                throw new InvalidArgumentException('Status kasbon sudah berubah — muat ulang.');
            }

            $jurnal = $this->jurnalService->postJurnal([
                'tanggal' => now(),
                'deskripsi' => 'Pencairan kasbon #'.$locked->id.' — '.$locked->employee?->nama,
                'tipe_sumber' => 'payroll',
                'ref_type' => 'PinjamanPegawai',
                'ref_id' => (string) $locked->id, // non-null → dedup pencairan 2×.
                'dibuat_oleh' => $userId,
                'baris' => [
                    ['akun_id' => $piutang->id, 'debit' => (float) $locked->nominal, 'kredit' => 0, 'memo' => 'Piutang kasbon pegawai'],
                    ['akun_id' => $kas->id, 'debit' => 0, 'kredit' => (float) $locked->nominal, 'memo' => 'Pencairan kasbon'],
                ],
            ]);

            $locked->update(['status' => 'berjalan', 'jurnal_pencairan_id' => $jurnal->id]);

            return $locked->fresh();
        });
    }

    /**
     * Pelunasan dipercepat (manual): terima nominal, posting D Kas / K Piutang
     * Pegawai, kurangi sisa_pokok; sisa 0 → lunas. Kunci idempotensi
     * deterministik dari sisa SEBELUM bayar (monoton turun) — klik ganda
     * nominal sama tak menggandakan jurnal.
     */
    public function lunasi(PinjamanPegawai $pinjaman, float $nominal, ?int $userId = null): PinjamanPegawai
    {
        $this->ensureTenantContext();

        $nominal = round($nominal, 2);
        if ($nominal <= 0) {
            throw new InvalidArgumentException('Nominal pelunasan harus lebih besar dari 0.');
        }

        $piutang = $this->akunPiutangPegawai();
        $kas = Akun::kasBank()->where('is_postable', true)->orderBy('kode')->first();
        if ($kas === null) {
            throw new RuntimeException('Akun kas belum tersedia (COA belum di-seed).');
        }

        return DB::transaction(function () use ($pinjaman, $nominal, $userId, $piutang, $kas): PinjamanPegawai {
            $locked = PinjamanPegawai::query()->whereKey($pinjaman->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'berjalan') {
                throw new InvalidArgumentException('Hanya kasbon berstatus berjalan yang dapat dilunasi.');
            }
            $sisaSebelum = round((float) $locked->sisa_pokok, 2);
            if ($nominal > $sisaSebelum) {
                throw new InvalidArgumentException('Nominal pelunasan melebihi sisa pokok ('.number_format($sisaSebelum, 2).').');
            }

            $this->jurnalService->postJurnal([
                'tanggal' => now(),
                'deskripsi' => 'Pelunasan dipercepat kasbon #'.$locked->id.' — '.$locked->employee?->nama,
                'tipe_sumber' => 'payroll',
                'ref_type' => 'PinjamanPegawaiPelunasan',
                // sisa monoton turun → kunci unik per pembayaran sah, dedup klik ganda.
                'ref_id' => $locked->id.':'.number_format($sisaSebelum, 2, '.', ''),
                'dibuat_oleh' => $userId,
                'baris' => [
                    ['akun_id' => $kas->id, 'debit' => $nominal, 'kredit' => 0, 'memo' => 'Pelunasan kasbon dipercepat'],
                    ['akun_id' => $piutang->id, 'debit' => 0, 'kredit' => $nominal, 'memo' => 'Pengurangan piutang kasbon'],
                ],
            ]);

            $sisaBaru = round($sisaSebelum - $nominal, 2);
            $locked->update([
                'sisa_pokok' => $sisaBaru,
                'status' => $sisaBaru <= 0.0 ? 'lunas' : 'berjalan',
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Akun Piutang Pegawai (1-1400). COA lama (tenant ter-seed sebelum WP-23)
     * belum punya akun ini — self-heal: buat idempoten di bawah 1-0000
     * (pelajaran 2b: jangan diam-diam memakai 1-1200 piutang santri).
     */
    public function akunPiutangPegawai(): Akun
    {
        $akun = Akun::where('kode', self::KODE_AKUN_PIUTANG_PEGAWAI)->first();
        if ($akun !== null) {
            return $akun;
        }

        $parent = Akun::where('kode', '1-0000')->first();
        if ($parent === null) {
            throw new RuntimeException('COA belum di-seed (akun 1-0000 tidak ditemukan) — jalankan LedgerSeeder.');
        }

        return Akun::updateOrCreate(
            ['kode' => self::KODE_AKUN_PIUTANG_PEGAWAI],
            [
                'nama' => 'Piutang Pegawai (Kasbon)',
                'tipe' => 'ASET',
                'saldo_normal' => Akun::SALDO_NORMAL['ASET'],
                'parent_id' => $parent->id,
                'is_kas_bank' => false,
                'is_postable' => true,
                'is_active' => true,
            ],
        );
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk kasbon.');
        }
    }
}
