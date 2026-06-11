<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Services\ApprovalService;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kesantrian\Models\PelanggaranSantri;
use Modules\Kesantrian\Models\PresensiAsrama;
use Modules\Ledger\Services\BukuBesarService;
use Modules\Ledger\Services\LaporanService;
use Modules\Payroll\Models\PresensiStaf;
use RuntimeException;

/**
 * WP-35 — Dasbor & Approval Pimpinan dari HP (rute /pimpinan, Inertia ringan).
 *
 * Baca-saja KECUALI aksi setujui/tolak ApprovalRequest, yang HANYA memanggil
 * ApprovalService (engine F1) — tidak ada logika approval baru di sini.
 * Antrean approval-rule hanya tampak bagi user yang MEMEGANG role approver
 * aturan tsb (pimpinan non-approver tidak melihat antrean itu).
 */
class PimpinanPwaController extends Controller
{
    public function beranda()
    {
        return Inertia::render('Pimpinan/Pimpinan', [
            'userName' => Auth::user()?->name,
        ]);
    }

    public function ringkasan(LaporanService $laporan, BukuBesarService $bukuBesar): JsonResponse
    {
        $today = now()->toDateString();

        // Kas hari ini — reuse query dasbor PWA Keuangan (dashboardPimpinan).
        $lr = $laporan->labaRugi();
        $saldoKas = $bukuBesar->bku()['saldo_akhir'];

        return response()->json([
            'kas' => [
                'saldo' => $saldoKas,
                'pendapatan' => $lr['total_pendapatan'],
                'beban' => $lr['total_beban'],
                'surplus_defisit' => $lr['surplus_defisit'],
            ],
            'kehadiran' => [
                'santri' => $this->persenKehadiranSantri($today),
                'guru' => $this->persenKehadiranGuru($today),
            ],
            'mutu' => $this->lampuMutu(),
            'antrean' => $this->antrean(),
        ]);
    }

    public function setujui(ApprovalRequest $approval, ApprovalService $service): JsonResponse
    {
        $this->pastikanBerwenang($approval);

        try {
            $service->setujui($approval, (int) Auth::id());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'disetujui']);
    }

    public function tolak(ApprovalRequest $approval, Request $request, ApprovalService $service): JsonResponse
    {
        $this->pastikanBerwenang($approval);

        // Validasi manual: rute /pimpinan di luar shouldRenderJsonWhen('api/*'),
        // ValidationException akan dirender sbg redirect HTML — balas JSON langsung.
        $alasan = $request->input('alasan');
        if (! is_string($alasan) || trim($alasan) === '' || mb_strlen($alasan) > 500) {
            return response()->json(['message' => 'Alasan penolakan wajib diisi (maks 500 karakter).'], 422);
        }
        $data = ['alasan' => trim($alasan)];

        try {
            $service->tolak($approval, (int) Auth::id(), $data['alasan']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'ditolak']);
    }

    /** Gate per-rule: hanya pemegang role approver aturan (atau super_admin). */
    private function pastikanBerwenang(ApprovalRequest $approval): void
    {
        $user = Auth::user();

        abort_unless(
            $user !== null && ($user->hasRole('super_admin') || ($approval->approver_role && $user->hasRole($approval->approver_role))),
            403,
            'Anda bukan approver untuk aturan ini.',
        );
    }

    /** % hadir santri hari ini dari gabungan presensi kelas + asrama. */
    private function persenKehadiranSantri(string $tanggal): array
    {
        $kelas = PresensiSantri::query()->whereDate('tanggal', $tanggal);
        $asrama = PresensiAsrama::query()->whereDate('tanggal', $tanggal);

        $total = (clone $kelas)->count() + (clone $asrama)->count();
        $hadir = (clone $kelas)->where('status', 'hadir')->count()
            + (clone $asrama)->where('status', 'hadir')->count();

        return [
            'total' => $total,
            'hadir' => $hadir,
            'persen' => $total > 0 ? round($hadir / $total * 100, 1) : null,
        ];
    }

    private function persenKehadiranGuru(string $tanggal): array
    {
        $q = PresensiStaf::query()->whereDate('tanggal', $tanggal);
        $total = (clone $q)->count();
        $hadir = (clone $q)->where('status', 'hadir')->count();

        return [
            'total' => $total,
            'hadir' => $hadir,
            'persen' => $total > 0 ? round($hadir / $total * 100, 1) : null,
        ];
    }

    /** Lampu mutu — DEFENSIF: tabel sedang dibangun paralel; null bila belum ada. */
    private function lampuMutu(): ?array
    {
        if (! Schema::hasTable('mutu_lampu')) {
            return null;
        }

        return DB::table('mutu_lampu')
            ->where('tenant_id', tenant('id'))
            ->select('warna', DB::raw('count(*) as jumlah'))
            ->groupBy('warna')
            ->pluck('jumlah', 'warna')
            ->all();
    }

    private function antrean(): array
    {
        $user = Auth::user();

        // Antrean approval-rule: HANYA request yang role approver-nya dipegang user.
        $approvals = collect();
        if ($user !== null) {
            $roleNames = $user->hasRole('super_admin')
                ? null // super_admin melihat semua
                : $user->getRoleNames()->all();

            $q = ApprovalRequest::query()->menunggu()->latest('id')->limit(20);
            if ($roleNames !== null) {
                $q->whereIn('approver_role', $roleNames === [] ? ['__none__'] : $roleNames);
            }

            $approvals = $q->get()->map(fn (ApprovalRequest $r) => [
                'id' => $r->id,
                'tipe' => class_basename($r->approvable_type),
                'approvable_id' => $r->approvable_id,
                'approver_role' => $r->approver_role,
                'diajukan_pada' => $r->created_at?->toDateTimeString(),
            ]);
        }

        return [
            'approval' => $approvals->values(),
            'izin_diajukan' => IzinSantri::query()->where('status', 'diajukan')->count(),
            'pelanggaran_menunggu' => PelanggaranSantri::query()->where('status', 'dicatat')->count(),
        ];
    }
}
