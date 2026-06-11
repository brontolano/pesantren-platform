<?php

namespace Modules\Payroll\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Akademik\Services\KalenderService;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Services\JurnalService;
use Modules\Ledger\Support\FiturKeuangan;
use Modules\Pajak\Services\PajakPenghasilanService;
use Modules\Payroll\Events\SlipGajiFinal;
use Modules\Payroll\Models\Cuti;
use Modules\Payroll\Models\Employee;
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
    /**
     * WP-14 — pembagi hari kerja utk potongan cuti tak berbayar (kebijakan per
     * tenant): 'kalender_efektif' (default — hitung dari KalenderService utk
     * bulan periode) ATAU angka tetap (mis. '26', '30').
     */
    public const SETTING_HARI_KERJA_PEMBAGI = 'payroll.hari_kerja_pembagi';

    public function __construct(private readonly JurnalService $jurnalService) {}

    /**
     * G2-10 — Buat slip dari STRUKTUR GAJI employee (komponen konfigurabel).
     * Membaca strukturGaji() (honor_pokok=Σtetap, tunjangan=Σtunjangan) lalu
     * mendelegasikan ke buatSlip() — math slip TIDAK diubah. Potongan struktur
     * belum dipotong di sini (BPJS/PPh21 menyusul G2-12).
     *
     * @param  array{poin?:int, honor_output?:float, created_by?:?int}  $opsi
     */
    public function buatSlipUntukEmployee(Employee $employee, string $periode, array $opsi = []): SlipGaji
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

    /**
     * G2-12 — Terapkan potongan PPh21 (tiered PTKP) + BPJS ke slip (OPT-IN).
     * Override pph21_dipotong + bpjs_dipotong, hitung ulang neto = bruto − pph21
     * − bpjs. TIDAK mengubah buatSlip()/bruto. Hanya slip draft.
     */
    public function terapkanPotongan(SlipGaji $slip, Employee $employee): SlipGaji
    {
        $this->ensureTenantContext();
        if ($slip->status === 'final') {
            throw new InvalidArgumentException('Slip sudah final — potongan tak dapat diubah.');
        }

        $bruto = (float) $slip->bruto;
        $pph21 = app(PajakPenghasilanService::class)
            ->pph21Bulanan($bruto, $employee->ptkp_status ?? 'TK0');
        $bpjs = app(BpjsService::class)->potonganPekerja($bruto);
        // WP-14: potongan cuti tak berbayar — 0 bila tak ada cuti tak berbayar,
        // sehingga math PPh21/BPJS/neto existing TIDAK berubah satu rupiah pun.
        $potonganCuti = $this->hitungPotonganCuti($slip, $employee);
        $neto = round($bruto - $pph21 - $bpjs - $potonganCuti, 2);
        if ($neto < 0) {
            throw new InvalidArgumentException('Potongan (PPh21 + BPJS + cuti) melebihi bruto — neto tidak boleh negatif.');
        }

        $slip->update([
            'pph21_dipotong' => $pph21,
            'bpjs_dipotong' => $bpjs,
            'potongan_cuti' => $potonganCuti,
            'neto' => $neto,
        ]);

        return $slip->fresh();
    }

    /**
     * WP-14 — Potongan cuti tak berbayar utk bulan periode slip:
     *   potongan = hari_tak_berbayar × (honor_pokok ÷ hari_kerja_periode)
     *
     * - Hanya cuti DISETUJUI + is_berbayar=false yang beririsan bulan periode.
     * - Hari cuti yang jatuh pada hari TIDAK efektif (libur nasional/pondok/
     *   ramadhan menurut KalenderService + libur mingguan Setting) TIDAK
     *   memotong — dihitung per tanggal via hariEfektifDalamRentang(d, d).
     * - Cuti lintas dua periode: hanya hari dalam bulan periode slip ini yang
     *   dihitung (proporsional per periode).
     * - Pembagi = Setting payroll.hari_kerja_pembagi: 'kalender_efektif'
     *   (default) → hari efektif bulan itu dari KalenderService; atau angka
     *   tetap. Pembagi tak valid/0 → potongan 0 (konservatif, tak bagi nol).
     * - Pembulatan SEKALI di akhir: half-up ke rupiah utuh.
     */
    public function hitungPotonganCuti(SlipGaji $slip, Employee $employee): float
    {
        try {
            // '!Y-m' me-reset hari ke 1 — tanpa '!', parsing di tanggal 29-31
            // mewarisi hari-ini dan meluap ke bulan berikut (potongan hilang).
            $awal = Carbon::createFromFormat('!Y-m', trim((string) $slip->periode))->startOfMonth()->startOfDay();
        } catch (\Throwable) {
            return 0.0; // periode bukan format YYYY-MM — tak ada basis bulan, jangan memotong.
        }
        $akhir = $awal->copy()->endOfMonth()->startOfDay();

        $daftarCuti = Cuti::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'disetujui')
            ->where('is_berbayar', false)
            ->whereDate('tanggal_mulai', '<=', $akhir)
            ->whereDate('tanggal_selesai', '>=', $awal)
            ->get();
        if ($daftarCuti->isEmpty()) {
            return 0.0;
        }

        $kalender = app(KalenderService::class);

        // Union tanggal (anti hitung-ganda bila rentang cuti tumpang tindih),
        // dipotong ke dalam bulan periode (proporsional lintas periode).
        $tanggalCuti = [];
        foreach ($daftarCuti as $cuti) {
            $dari = $cuti->tanggal_mulai->copy()->max($awal);
            $sampai = $cuti->tanggal_selesai->copy()->min($akhir);
            for ($hari = $dari->copy(); $hari->lte($sampai); $hari->addDay()) {
                $tanggalCuti[$hari->toDateString()] = true;
            }
        }

        $hariTakBerbayar = 0;
        foreach (array_keys($tanggalCuti) as $tanggal) {
            $d = Carbon::parse($tanggal);
            // hariEfektifDalamRentang(d, d) = 1 bila efektif (cek entri kalender
            // + libur mingguan Setting), 0 bila tidak → hari libur tak memotong.
            $hariTakBerbayar += $kalender->hariEfektifDalamRentang($d, $d);
        }
        if ($hariTakBerbayar <= 0) {
            return 0.0;
        }

        $pembagi = $this->hariKerjaPembagi($kalender, $awal, $akhir);
        if ($pembagi <= 0) {
            return 0.0;
        }

        // DECIMAL math, pembulatan SEKALI: half-up ke rupiah utuh.
        return round($hariTakBerbayar * (float) $slip->honor_pokok / $pembagi, 0, PHP_ROUND_HALF_UP);
    }

    /** WP-14 — pembagi hari kerja periode dari kebijakan tenant. */
    private function hariKerjaPembagi(KalenderService $kalender, Carbon $awal, Carbon $akhir): float
    {
        $nilai = Setting::getTenantValue(self::SETTING_HARI_KERJA_PEMBAGI, 'kalender_efektif');
        if (is_array($nilai)) {
            $nilai = $nilai['value'] ?? 'kalender_efektif';
        }

        if (is_numeric($nilai)) {
            $angka = (float) $nilai;
            if ($angka >= 1 && $angka <= 31) {
                return $angka; // angka tetap, mis. 26 / 30.
            }
            // 0/negatif/raksasa = salah konfigurasi — jangan diam-diam
            // mematikan potongan se-tenant; fallback kalender + log.
            Log::warning("payroll.hari_kerja_pembagi tak wajar ({$nilai}) — fallback kalender_efektif.");
        }

        return (float) $kalender->hariEfektifDalamRentang($awal, $akhir);
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

        // Slip bulanan WAJIB periode YYYY-MM — 'THR-2026' dkk lewat ThrService
        // (mencegah slip bulanan menduduki slot periode THR; WP-15).
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $periode)) {
            throw new InvalidArgumentException("Periode slip bulanan harus berformat YYYY-MM, diterima: {$periode}.");
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
        // G2-12: utang BPJS (fallback ke utang PPh/kas agar jurnal tetap seimbang
        // bila akun BPJS belum di-seed).
        $utangBpjs = Akun::where('kode', '2-1160')->first() ?? $utangPph ?? $kas;
        if ($bebanGaji === null || $kas === null) {
            throw new RuntimeException('Akun beban gaji / kas belum tersedia (COA belum di-seed).');
        }

        return DB::transaction(function () use ($slip, $dibuatOleh, $bebanGaji, $utangPph, $utangBpjs, $kas): SlipGaji {
            // WP-14: cuti tak berbayar yang disetujui SETELAH terapkanPotongan
            // tapi SEBELUM finalisasi tidak boleh lolos (TOCTOU) — hitung ulang
            // dan tolak bila angka tersimpan sudah basi.
            $employee = Employee::where('users_id', $slip->users_id)->first();
            if ($employee !== null) {
                $potonganTerkini = $this->hitungPotonganCuti($slip, $employee);
                if (round($potonganTerkini) !== round((float) $slip->potongan_cuti)) {
                    throw new RuntimeException(
                        'Potongan cuti slip basi (tersimpan '.round((float) $slip->potongan_cuti)
                        .', terkini '.round($potonganTerkini)
                        .') — jalankan Terapkan Potongan ulang sebelum finalisasi.',
                    );
                }
            }

            $baris = [
                ['akun_id' => $bebanGaji->id, 'debit' => (float) $slip->bruto, 'kredit' => 0, 'memo' => 'Beban gaji/honor'],
            ];
            if ((float) $slip->pph21_dipotong > 0 && $utangPph !== null) {
                $baris[] = ['akun_id' => $utangPph->id, 'debit' => 0, 'kredit' => (float) $slip->pph21_dipotong, 'memo' => 'Utang PPh21'];
            }
            // G2-12: potongan BPJS = liabilitas (agar Debit bruto = total Kredit).
            if ((float) $slip->bpjs_dipotong > 0 && $utangBpjs !== null) {
                $baris[] = ['akun_id' => $utangBpjs->id, 'debit' => 0, 'kredit' => (float) $slip->bpjs_dipotong, 'memo' => 'Utang BPJS (potongan pekerja)'];
            }
            // WP-14: potongan cuti tak berbayar = pengurang beban gaji (kontra
            // di akun yang sama) — bukan liabilitas; baris ADDITIF (hanya muncul
            // bila > 0) agar jurnal slip tanpa cuti tak berbayar identik.
            if ((float) $slip->potongan_cuti > 0) {
                $baris[] = ['akun_id' => $bebanGaji->id, 'debit' => 0, 'kredit' => (float) $slip->potongan_cuti, 'memo' => 'Potongan cuti tak berbayar'];
            }
            $baris[] = ['akun_id' => $kas->id, 'debit' => 0, 'kredit' => (float) $slip->neto, 'memo' => 'Pembayaran gaji neto'];

            $jurnal = $this->jurnalService->postJurnal([
                'tanggal' => now(),
                'deskripsi' => 'Gaji '.$slip->periode.' — PTK #'.$slip->users_id,
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
