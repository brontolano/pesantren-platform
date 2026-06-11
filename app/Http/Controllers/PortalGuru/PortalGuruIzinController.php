<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalGuru;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalGuru\Concerns\ResolvesPortalGuruContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Payroll\Models\Cuti;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Services\IzinPegawaiService;

/**
 * WP-17 — Izin Pegawai Harian di PWA Guru (menumpang Portal Guru F1).
 * Pegawai ajukan + lihat status izinnya; approver (kepala lembaga hasil
 * resolusi IzinPegawaiService) melihat antrean & setujui/tolak dari PWA juga.
 * Idempoten via client_ref UUID (pola WP-26/WP-32 offline-sync).
 */
class PortalGuruIzinController extends Controller
{
    use ResolvesPortalGuruContext;

    public function __construct(private readonly IzinPegawaiService $izinService) {}

    public function index(Request $request): Response
    {
        $guru = $this->currentGuru($request);
        $employee = $this->employeeUntuk($guru->id);

        $izinSaya = $employee === null ? collect() : Cuti::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('tanggal_mulai')
            ->limit(30)
            ->get()
            ->map(fn (Cuti $c): array => $this->formatIzin($c));

        return Inertia::render('PortalGuru/Izin', [
            ...$this->sharedProps($request),
            'employee' => $employee === null ? null : ['id' => $employee->id, 'nama' => $employee->nama],
            'jenisIzin' => collect(Cuti::JENIS_IZIN_HARIAN)
                ->map(fn (string $j): array => ['value' => $j, 'label' => Cuti::JENIS[$j]])->values(),
            'izinSaya' => $izinSaya,
            // Antrean hanya bagi user yang ter-resolve sebagai approver.
            'antrean' => $this->izinService->antreanUntuk($guru)
                ->map(fn (Cuti $c): array => $this->formatIzin($c))->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $guru = $this->currentGuru($request);
        $employee = $this->employeeUntuk($guru->id);
        abort_if($employee === null, 422, 'Akun Anda belum tertaut data pegawai (HR).');

        $data = $request->validate([
            'jenis' => ['required', 'in:'.implode(',', Cuti::JENIS_IZIN_HARIAN)],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'alasan' => ['nullable', 'string', 'max:500'],
            'client_ref' => ['required', 'uuid'],
        ]);

        $this->izinService->ajukan($employee, $data, $guru->id);

        return back()->with('success', 'Izin diajukan.');
    }

    public function setujui(Request $request, int $cuti): RedirectResponse
    {
        return $this->putuskan($request, $cuti, 'setujui');
    }

    public function tolak(Request $request, int $cuti): RedirectResponse
    {
        return $this->putuskan($request, $cuti, 'tolak');
    }

    private function putuskan(Request $request, int $cutiId, string $aksi): RedirectResponse
    {
        $guru = $this->currentGuru($request);
        $cuti = Cuti::query()->findOrFail($cutiId);

        // PWA hanya memutus izin harian — cuti tahunan/sakit lewat alur panel.
        abort_unless(in_array($cuti->jenis, Cuti::JENIS_IZIN_HARIAN, true), 403);

        try {
            $aksi === 'setujui'
                ? $this->izinService->setujui($cuti, $guru)
                : $this->izinService->tolak($cuti, $guru, $request->input('alasan'));
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        }

        return back()->with('success', $aksi === 'setujui' ? 'Izin disetujui.' : 'Izin ditolak.');
    }

    private function employeeUntuk(int $userId): ?Employee
    {
        return Employee::query()->where('users_id', $userId)->where('aktif', true)->first();
    }

    private function formatIzin(Cuti $c): array
    {
        return [
            'id' => $c->id,
            'employee' => $c->employee?->nama,
            'jenis' => $c->jenis,
            'jenis_label' => Cuti::JENIS[$c->jenis] ?? $c->jenis,
            'tanggal_mulai' => $c->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $c->tanggal_selesai?->toDateString(),
            'jumlah_hari' => $c->jumlah_hari,
            'alasan' => $c->alasan,
            'status' => $c->status,
        ];
    }
}
