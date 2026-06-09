<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Services\JurnalService;
use Modules\Ledger\Support\FiturKeuangan;
use Modules\Payroll\Events\SlipGajiFinal;
use Modules\Payroll\Models\SlipGaji;
use RuntimeException;

/**
 * Payroll asatidz berbasis khidmah (poin/KPI).
 *   Bruto = honor_pokok + tunjangan + (poin × tarif_poin) + honor_output
 *   PPh21 = bruto × tarif (configurable, default 0%)  → Neto = Bruto − PPh21
 * Jurnal final: D Beban Gaji (5-2000) / K Utang PPh21 (2-1120) / K Kas.
 */
class PayrollService
{
    public function __construct(private readonly JurnalService $jurnalService)
    {
    }

    /**
     * G2-10 — Buat slip dari STRUKTUR GAJI employee (komponen konfigurabel).
     * Membaca strukturGaji() (honor_pokok=Σtetap, tunjangan=Σtunjangan) lalu
     * mendelegasikan ke buatSlip() — math slip TIDAK diubah. Potongan struktur
     * belum dipotong di sini (BPJS/PPh21 menyusul G2-12).
     *
     * @param  array{poin?:int, honor_output?:float, created_by?:?int}  $opsi
     */
    public function buatSlipUntukEmployee(\Modules\Payroll\Models\Employee $employee, string $periode, array $opsi = []): SlipGaji
    {
        $this->ensureTenantContext();
        if (! $employee->users_id) {
            throw new InvalidArgumentException('Employee belum tertaut akun pengguna (users_id) untuk slip gaji.');
        }

        $struktur = $employee->strukturGaji();

        return $this->buatSlip([
            'users_id' => $employee->users_id,
            'periode' => $periode,
            'honor_pokok' => $struktur['honor_pokok'],
            'tunjangan' => $struktur['tunjangan'],
            'poin' => $opsi['poin'] ?? 0,
            'honor_output' => $opsi['honor_output'] ?? 0,
            'created_by' => $opsi['created_by'] ?? null,
        ]);
    }

    /** Buat slip (status draft) dengan perhitungan otomatis. Idempoten per (users_id, periode). */
    public function buatSlip(array $data): SlipGaji
    {
        $this->ensureTenantContext();

        $usersId = $data['users_id'] ?? null;
        $periode = $data['periode'] ?? null;
        if (! $usersId || ! $periode) {
            throw new InvalidArgumentException('users_id dan periode wajib diisi.');
        }

        $existing = SlipGaji::where('users_id', $usersId)->where('periode', $periode)->first();
        if ($existing !== null) {
            return $existing; // idempoten — satu slip per PTK per periode.
        }

        $honorPokok = round((float) ($data['honor_pokok'] ?? 0), 2);
        $tunjangan = round((float) ($data['tunjangan'] ?? 0), 2);
        $poin = (int) ($data['poin'] ?? 0);
        $tarifPoin = isset($data['tarif_poin']) ? (float) $data['tarif_poin'] : FiturKeuangan::tarifPoin();
        $honorOutput = round((float) ($data['honor_output'] ?? 0), 2);

        $honorPoin = round($poin * $tarifPoin, 2);
        $bruto = round($honorPokok + $tunjangan + $honorPoin + $honorOutput, 2);
        $pph21 = round($bruto * FiturKeuangan::pph21Persen() / 100, 2);
        $neto = round($bruto - $pph21, 2);

        return SlipGaji::create([
            'users_id' => $usersId,
            'periode' => $periode,
            'honor_pokok' => $honorPokok,
            'tunjangan' => $tunjangan,
            'poin' => $poin,
            'tarif_poin' => $tarifPoin,
            'honor_poin' => $honorPoin,
            'honor_output' => $honorOutput,
            'bruto' => $bruto,
            'pph21_dipotong' => $pph21,
            'neto' => $neto,
            'status' => 'draft',
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /** Finalisasi: posting jurnal idempoten + status final + event. */
    public function finalisasi(SlipGaji $slip, ?int $dibuatOleh = null): SlipGaji
    {
        $this->ensureTenantContext();

        if ($slip->status === 'final' && $slip->jurnal_id) {
            return $slip;
        }

        // G1-01: pagar persetujuan (F1). NO-OP tanpa ApprovalRule wajib (field 'bruto')
        // terpicu; melempar bila wajib & belum disetujui — sebelum posting jurnal.
        $slip->pastikanBolehLanjut();

        $bebanGaji = Akun::where('kode', '5-2000')->first();
        $utangPph = Akun::where('kode', '2-1120')->first();
        $kas = Akun::kasBank()->where('is_postable', true)->orderBy('kode')->first();
        if ($bebanGaji === null || $kas === null) {
            throw new RuntimeException('Akun beban gaji / kas belum tersedia (COA belum di-seed).');
        }

        return DB::transaction(function () use ($slip, $dibuatOleh, $bebanGaji, $utangPph, $kas): SlipGaji {
            $baris = [
                ['akun_id' => $bebanGaji->id, 'debit' => (float) $slip->bruto, 'kredit' => 0, 'memo' => 'Beban gaji/honor'],
            ];
            if ((float) $slip->pph21_dipotong > 0 && $utangPph !== null) {
                $baris[] = ['akun_id' => $utangPph->id, 'debit' => 0, 'kredit' => (float) $slip->pph21_dipotong, 'memo' => 'Utang PPh21'];
            }
            $baris[] = ['akun_id' => $kas->id, 'debit' => 0, 'kredit' => (float) $slip->neto, 'memo' => 'Pembayaran gaji neto'];

            $jurnal = $this->jurnalService->postJurnal([
                'tanggal' => now(),
                'deskripsi' => 'Gaji ' . $slip->periode . ' — PTK #' . $slip->users_id,
                'tipe_sumber' => 'payroll',
                'ref_type' => 'SlipGaji',
                'ref_id' => (string) $slip->id,
                'dibuat_oleh' => $dibuatOleh,
                'baris' => $baris,
            ]);

            $slip->update(['status' => 'final', 'jurnal_id' => $jurnal->id]);
            SlipGajiFinal::dispatch($slip->fresh());

            return $slip->fresh();
        });
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
