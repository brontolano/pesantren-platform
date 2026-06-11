<?php

namespace Modules\Payroll\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Services\ApprovalService;
use Modules\Payroll\Models\Cuti;
use RuntimeException;

/**
 * G2-11 — Pengajuan & persetujuan cuti (leave). Approval lewat F1 (trait
 * Approvable pada model Cuti). Anti-tumpang-tindih: tak bikin engine approval sendiri.
 */
class CutiService
{
    /** WP-14 — kebijakan default berbayar/tidak utk jenis cuti baru (per tenant). */
    public const SETTING_CUTI_DEFAULT_BERBAYAR = 'payroll.cuti_default_berbayar';

    /** Ajukan cuti: hitung jumlah hari (inklusif) + buat 'diajukan' + F1 ajukanPersetujuan. */
    public function ajukan(int $employeeId, string $jenis, string $mulai, string $selesai, ?string $alasan = null, ?int $userId = null, ?bool $isBerbayar = null, ?string $clientRef = null): Cuti
    {
        $this->ensureTenantContext();

        $tglMulai = Carbon::parse($mulai)->startOfDay();
        $tglSelesai = Carbon::parse($selesai)->startOfDay();
        if ($tglSelesai->lt($tglMulai)) {
            throw new InvalidArgumentException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }
        $jumlahHari = $tglMulai->diffInDays($tglSelesai) + 1; // inklusif

        // client_ref ikut di INSERT (bukan forceFill susulan) — double-submit
        // paralel pecah di unique DB tanpa meninggalkan baris yatim.
        $cuti = Cuti::create([
            'employee_id' => $employeeId,
            'jenis' => $jenis,
            'is_berbayar' => $isBerbayar ?? self::defaultBerbayar(),
            'tanggal_mulai' => $tglMulai->toDateString(),
            'tanggal_selesai' => $tglSelesai->toDateString(),
            'jumlah_hari' => $jumlahHari,
            'alasan' => $alasan,
            'status' => 'diajukan',
            'diajukan_oleh' => $userId,
            'client_ref' => $clientRef,
        ]);

        $cuti->ajukanPersetujuan($userId); // F1: buat request bila ada rule wajib

        return $cuti->fresh();
    }

    /** Setujui cuti: pagar F1 (no-op tanpa rule) → status disetujui. */
    public function setujui(Cuti $cuti, ?int $userId = null): Cuti
    {
        $this->ensureTenantContext();
        if ($cuti->status !== 'diajukan') {
            throw new InvalidArgumentException('Hanya cuti berstatus diajukan yang dapat disetujui.');
        }

        $cuti->pastikanBolehLanjut();

        // Selaraskan ApprovalRequest (bila ada) agar jejak konsisten.
        if ($req = $cuti->approvalRequests()->where('status', 'menunggu')->latest('id')->first()) {
            app(ApprovalService::class)->setujui($req, (int) ($userId ?? 0));
        }

        // Conditional update: dua keputusan paralel — hanya satu yang menang.
        $diubah = Cuti::query()->whereKey($cuti->id)->where('status', 'diajukan')
            ->update(['status' => 'disetujui', 'disetujui_oleh' => $userId, 'disetujui_pada' => now()]);
        if ($diubah === 0) {
            throw new InvalidArgumentException('Status cuti sudah berubah — muat ulang.');
        }

        return $cuti->fresh();
    }

    /** Tolak cuti. */
    public function tolak(Cuti $cuti, ?int $userId = null, ?string $alasan = null): Cuti
    {
        $this->ensureTenantContext();
        if ($cuti->status !== 'diajukan') {
            throw new InvalidArgumentException('Hanya cuti berstatus diajukan yang dapat ditolak.');
        }

        if ($req = $cuti->approvalRequests()->where('status', 'menunggu')->latest('id')->first()) {
            app(ApprovalService::class)->tolak($req, (int) ($userId ?? 0), $alasan ?? 'Ditolak');
        }

        $diubah = Cuti::query()->whereKey($cuti->id)->where('status', 'diajukan')
            ->update(['status' => 'ditolak', 'disetujui_oleh' => $userId, 'disetujui_pada' => now()]);
        if ($diubah === 0) {
            throw new InvalidArgumentException('Status cuti sudah berubah — muat ulang.');
        }

        return $cuti->fresh();
    }

    /** WP-14 — default is_berbayar dari Setting tenant (default TRUE = berbayar, konservatif). */
    public static function defaultBerbayar(): bool
    {
        $nilai = Setting::getTenantValue(self::SETTING_CUTI_DEFAULT_BERBAYAR, true);
        if (is_array($nilai)) {
            $nilai = $nilai['value'] ?? true;
        }

        return filter_var($nilai, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk cuti.');
        }
    }
}
