<?php

namespace Modules\Pengadaan\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Models\SumberDana;
use Modules\Ledger\Services\AnggaranGuardService;
use Modules\Ledger\Services\JurnalService;
use Modules\Pajak\Services\MesinPajakService;
use Modules\Pengadaan\Events\BelanjaDisetujui;
use Modules\Pengadaan\Models\Belanja;
use RuntimeException;

/**
 * Pencatatan belanja → hitung pajak otomatis → auto-jurnal idempoten via JurnalService.
 *
 * Pembukuan (bendahara pemungut):
 *   Debit  Beban           (dpp + ppn)
 *   Kredit Utang PPN        (ppn)        — bila ada
 *   Kredit Utang PPh        (pph)        — bila ada
 *   Kredit Kas              (dpp - pph)  — dibayar ke vendor neto PPh
 */
class BelanjaService
{
    /** Pemetaan jenis PPh → kode akun utang pajak (sesuai COA seed). */
    private const AKUN_UTANG_PPH = [
        'pph21' => '2-1120',
        'pph22' => '2-1130',
        'pph23' => '2-1140',
        'pph_final_42' => '2-1150',
    ];

    private const AKUN_UTANG_PPN = '2-1110';

    public function __construct(
        private readonly MesinPajakService $mesinPajak,
        private readonly JurnalService $jurnalService,
    ) {}

    /** Buat belanja (status diajukan) dengan pajak terhitung otomatis. */
    public function buat(array $data): Belanja
    {
        $this->ensureTenantContext();

        // Idempotensi offline-sync: client_ref sama → kembalikan record yang sudah ada.
        $clientRef = $data['client_ref'] ?? null;
        if ($clientRef !== null) {
            $existing = Belanja::where('client_ref', $clientRef)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $dpp = round((float) ($data['dpp'] ?? 0), 2);
        if ($dpp <= 0) {
            throw new InvalidArgumentException('DPP belanja harus lebih dari 0.');
        }
        $jenis = $data['jenis'] ?? 'barang';

        $danaBos = $this->isDanaBos($data['sumber_dana_id'] ?? null);
        $vendorNpwp = (bool) ($data['vendor_npwp'] ?? false);
        $kehalalan = $this->kehalalanSumber($data['sumber_dana_id'] ?? null);

        // G1-04: dana syubhat/non-halal → pajak negara tidak dipungut.
        $pajak = $this->mesinPajak->hitung($dpp, $jenis, $danaBos, $vendorNpwp, $kehalalan);

        return Belanja::create([
            'client_ref' => $clientRef,
            'tanggal' => $data['tanggal'] ?? now()->toDateString(),
            'uraian' => $data['uraian'] ?? 'Belanja',
            'jenis' => $jenis,
            'kategori_rkam' => $data['kategori_rkam'] ?? null,
            'divisi_id' => $data['divisi_id'] ?? null,
            'sumber_dana_id' => $data['sumber_dana_id'] ?? null,
            'dpp' => $dpp,
            'vendor_npwp' => $vendorNpwp,
            'ppn_dipungut' => $pajak['ppn'],
            'pph_jenis' => $pajak['pph'] > 0 ? $pajak['pph_jenis'] : null,
            'pph_dipungut' => $pajak['pph'],
            'total' => round($dpp + $pajak['ppn'], 2),
            'akun_beban_id' => $data['akun_beban_id'] ?? null,
            'akun_kas_id' => $data['akun_kas_id'] ?? $this->akunKasDefault()?->id,
            'status_approval' => 'diajukan',
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /** Setujui belanja → posting jurnal idempoten → tandai dicairkan → pancarkan event. */
    public function setujui(Belanja $belanja, ?int $approvedBy = null): Belanja
    {
        $this->ensureTenantContext();

        if (in_array($belanja->status_approval, ['ditolak'], true)) {
            throw new InvalidArgumentException('Belanja sudah ditolak.');
        }

        // Guard anggaran RKAM (adaptasi Budget Control ERPNext). No-op bila tak ada
        // pagu untuk akun+dimensi+tahun; melempar bila pagu mode 'blokir' terlampaui.
        if ($belanja->akun_beban_id) {
            app(AnggaranGuardService::class)->assertBoleh(
                (int) $belanja->akun_beban_id,
                $belanja->divisi_id ? (int) $belanja->divisi_id : null,
                $belanja->sumber_dana_id ? (int) $belanja->sumber_dana_id : null,
                (string) Carbon::parse($belanja->tanggal)->year,
                (float) $belanja->total,
            );
        }

        return DB::transaction(function () use ($belanja, $approvedBy): Belanja {
            // G1-01: pagar persetujuan (F1). NO-OP bila tenant tak punya ApprovalRule
            // wajib terpicu untuk Belanja; melempar bila wajib & belum disetujui.
            $belanja->pastikanBolehLanjut();

            $jurnal = $this->posting($belanja, $approvedBy);

            $belanja->update([
                'status_approval' => 'dicairkan',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'jurnal_id' => $jurnal->id,
            ]);

            BelanjaDisetujui::dispatch($belanja->fresh());

            return $belanja->fresh();
        });
    }

    public function tolak(Belanja $belanja, ?int $approvedBy = null): Belanja
    {
        $this->ensureTenantContext();
        $belanja->update(['status_approval' => 'ditolak', 'approved_by' => $approvedBy, 'approved_at' => now()]);

        return $belanja->fresh();
    }

    /** Posting jurnal belanja (idempoten via ref Belanja). */
    public function posting(Belanja $belanja, ?int $dibuatOleh = null): Jurnal
    {
        $akunBeban = $belanja->akun_beban_id
            ? Akun::find($belanja->akun_beban_id)
            : Akun::where('kode', '5-4000')->first(); // default Beban Pengadaan Barang
        $akunKas = $belanja->akun_kas_id ? Akun::find($belanja->akun_kas_id) : $this->akunKasDefault();

        if ($akunBeban === null || $akunKas === null) {
            // Pesan ramah (tetap RuntimeException agar pemanggil yang menangkap
            // tipe ini tidak pecah). Sebut bagian COA yang belum siap.
            $kurang = [];
            if ($akunBeban === null) {
                $kurang[] = 'akun beban ('.($belanja->akun_beban_id ? 'akun beban terpilih tidak ditemukan' : 'beban default 5-4000 belum ada').')';
            }
            if ($akunKas === null) {
                $kurang[] = 'akun kas/bank yang bisa diposting';
            }

            throw new RuntimeException(
                'Bagan Akun (COA) belum lengkap untuk memposting belanja ini: '
                .implode(' dan ', $kurang)
                .'. Mohon lengkapi/seed Bagan Akun pesantren (menu Keuangan → Bagan Akun) sebelum mencairkan belanja.'
            );
        }

        $dpp = (float) $belanja->dpp;
        $ppn = (float) $belanja->ppn_dipungut;
        $pph = (float) $belanja->pph_dipungut;

        $baris = [];
        // Debit beban (dpp + ppn)
        $baris[] = $this->baris($akunBeban->id, $belanja, round($dpp + $ppn, 2), 0, 'Beban belanja');
        // Kredit utang PPN
        if ($ppn > 0) {
            $akunPpn = Akun::where('kode', self::AKUN_UTANG_PPN)->first();
            $baris[] = $this->baris($akunPpn?->id, $belanja, 0, $ppn, 'Utang PPN dipungut');
        }
        // Kredit utang PPh
        if ($pph > 0 && $belanja->pph_jenis) {
            $kode = self::AKUN_UTANG_PPH[$belanja->pph_jenis] ?? null;
            $akunPph = $kode ? Akun::where('kode', $kode)->first() : null;
            $baris[] = $this->baris($akunPph?->id, $belanja, 0, $pph, 'Utang PPh dipotong');
        }
        // Kredit kas (dpp - pph)
        $baris[] = $this->baris($akunKas->id, $belanja, 0, round($dpp - $pph, 2), 'Pembayaran ke vendor');

        return $this->jurnalService->postJurnal([
            'tanggal' => $belanja->tanggal,
            'deskripsi' => 'Belanja: '.$belanja->uraian,
            'tipe_sumber' => 'belanja',
            'ref_type' => 'Belanja',
            'ref_id' => (string) $belanja->id,
            'dibuat_oleh' => $dibuatOleh,
            'baris' => array_values(array_filter($baris, fn ($b) => $b['akun_id'] !== null)),
        ]);
    }

    /**
     * Pra-cek OPSIONAL (tidak mengubah posting/total/pajak): apakah akun beban &
     * kas yang diperlukan untuk memposting belanja ini sudah tersedia di COA?
     * Mengembalikan null bila siap, atau pesan ramah bila ada yang belum ada.
     * Dipakai UI/aksi uang untuk memberi peringatan dini sebelum mencairkan.
     */
    public function alasanCoaBelumSiap(Belanja $belanja): ?string
    {
        $akunBeban = $belanja->akun_beban_id
            ? Akun::find($belanja->akun_beban_id)
            : Akun::where('kode', '5-4000')->first();
        $akunKas = $belanja->akun_kas_id ? Akun::find($belanja->akun_kas_id) : $this->akunKasDefault();

        if ($akunBeban !== null && $akunKas !== null) {
            return null;
        }

        $kurang = [];
        if ($akunBeban === null) {
            $kurang[] = 'akun beban';
        }
        if ($akunKas === null) {
            $kurang[] = 'akun kas/bank';
        }

        return 'Bagan Akun (COA) belum lengkap: '.implode(' dan ', $kurang)
            .' belum tersedia. Lengkapi Bagan Akun (menu Keuangan → Bagan Akun) sebelum mencairkan belanja.';
    }

    private function baris(?int $akunId, Belanja $belanja, float $debit, float $kredit, string $memo): array
    {
        return [
            'akun_id' => $akunId,
            'divisi_id' => $belanja->divisi_id,
            'sumber_dana_id' => $belanja->sumber_dana_id,
            'debit' => $debit,
            'kredit' => $kredit,
            'memo' => $memo,
        ];
    }

    private function isDanaBos(?int $sumberDanaId): bool
    {
        if (! $sumberDanaId) {
            return false;
        }
        $sumber = SumberDana::find($sumberDanaId);

        return $sumber !== null && $sumber->kategori === 'negara';
    }

    /** G1-04: klasifikasi kehalalan sumber dana (default 'halal' bila tak ada). */
    private function kehalalanSumber(?int $sumberDanaId): string
    {
        if (! $sumberDanaId) {
            return 'halal';
        }

        return SumberDana::find($sumberDanaId)?->kehalalan ?? 'halal';
    }

    private function akunKasDefault(): ?Akun
    {
        return Akun::kasBank()->where('is_postable', true)->orderBy('kode')->first();
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
