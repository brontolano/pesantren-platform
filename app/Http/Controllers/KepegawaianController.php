<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Payroll\Models\Cuti;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\PresensiStaf;
use Modules\Payroll\Models\SlipGaji;

/**
 * PWA "Kepegawaian" — menggantikan KepegawaianDemoController (data dummy
 * inline). Data nyata via model Modules\Payroll yang SUDAH dipakai panel
 * admin (EmployeeResource/CutiResource/PresensiStafResource/SlipGajiResource
 * — Modules/Payroll/app/Filament/Resources/*): tak ada logika/skema baru.
 *
 * Gate: middleware('auth') + can:run_payroll — permission yang SUDAH
 * dimiliki role `petugas_payroll` (RolesAndPermissionsSeeder) dan dipakai
 * SEMUA resource Payroll di atas via shouldRegisterNavigation()/canAccess().
 * `petugas_payroll` sebelumnya TIDAK punya beranda PWA apa pun di
 * AppLauncher::STAFF_APPS (tidak match tile mana pun) — jadi tile baru ini
 * BUKAN duplikat/dead-code, ia mengisi kekosongan.
 *
 * Cakupan (jujur, tanpa fitur baru):
 * - Beranda: ringkasan jumlah pegawai aktif, kontrak akan berakhir (<=60 hari,
 *   dari Employee.tanggal_selesai — kolom yang SUDAH ada), slip periode terbaru.
 * - Pegawai: daftar & detail dari Employee (+ jabatanAktif() via PenugasanPegawai
 *   yang SUDAH ada).
 * - Absensi: daftar presensi staf HARI INI dari PresensiStaf (model nyata,
 *   sudah dipakai PresensiStafResource) — read-only (pencatatan tetap di admin).
 * - Slip: daftar SlipGaji tenant (read-only) — sama dgn SlipGajiResource,
 *   TIDAK menduplikasi mesin hitung PayrollService.
 * - Cuti: daftar pengajuan Cuti (read-only, status Approvable) — sama dgn
 *   CutiResource. TIDAK ada aksi setujui/tolak baru di sini: itu tetap di
 *   panel admin (Approvable/F1 approval workflow) agar tak menduplikasi alur
 *   approval lintas-modul yang sudah ada.
 */
class KepegawaianController extends Controller
{
    private function nav(): array
    {
        $base = '/kepegawaian';

        $items = [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => $base, 'route' => 'beranda', 'icon' => 'home'],
            ['key' => 'pegawai', 'label' => 'Pegawai', 'href' => "{$base}/pegawai", 'route' => 'pegawai', 'icon' => 'user'],
            ['key' => 'absensi', 'label' => 'Absensi', 'href' => "{$base}/absensi", 'route' => 'absensi', 'icon' => 'check-circle'],
            ['key' => 'slip', 'label' => 'Slip', 'href' => "{$base}/slip", 'route' => 'slip', 'icon' => 'receipt'],
            ['key' => 'cuti', 'label' => 'Cuti', 'href' => "{$base}/cuti", 'route' => 'cuti', 'icon' => 'calendar'],
        ];

        return collect($items)
            ->map(fn (array $i): array => [
                'key' => $i['key'],
                'label' => $i['label'],
                'href' => $i['href'],
                'icon' => $i['icon'],
                'active' => request()->routeIs("tenant.kepegawaian.{$i['route']}"),
            ])
            ->values()
            ->all();
    }

    public function beranda(): Response
    {
        $kontrakSegera = Employee::query()
            ->where('aktif', true)
            ->whereNotNull('tanggal_selesai')
            ->whereDate('tanggal_selesai', '>=', now()->toDateString())
            ->whereDate('tanggal_selesai', '<=', now()->addDays(60)->toDateString())
            ->orderBy('tanggal_selesai')
            ->limit(5)
            ->get()
            ->map(fn (Employee $e): array => [
                'nama' => $e->nama,
                'jabatan' => $e->jabatanAktif()?->nama ?? $e->jabatan,
                'sisa_hari' => (int) now()->startOfDay()->diffInDays($e->tanggal_selesai, false),
                'berakhir' => $e->tanggal_selesai?->translatedFormat('d M Y'),
            ])
            ->values();

        $periodeTerbaru = SlipGaji::query()->max('periode');

        return Inertia::render('Kepegawaian/Beranda', [
            'navigation' => $this->nav(),
            'userName' => Auth::user()?->name,
            'stats' => [
                'pegawai_aktif' => Employee::query()->where('aktif', true)->count(),
                'slip_periode_terbaru' => $periodeTerbaru,
                'kontrak_segera' => $kontrakSegera->count(),
                'cuti_menunggu' => Cuti::query()->where('status', 'diajukan')->count(),
            ],
            'kontrakSegera' => $kontrakSegera,
        ]);
    }

    public function pegawai(): Response
    {
        $pegawai = Employee::query()
            ->orderBy('nama')
            ->get()
            ->map(fn (Employee $e): array => [
                'id' => $e->id,
                'nama' => $e->nama,
                'nip' => $e->nip,
                'jabatan' => $e->jabatanAktif()?->nama ?? $e->jabatan,
                'status_kepegawaian' => Employee::labelStatusKepegawaian($e->status_kepegawaian),
                'aktif' => $e->aktif,
            ])
            ->values();

        return Inertia::render('Kepegawaian/Pegawai', [
            'navigation' => $this->nav(),
            'pegawai' => $pegawai,
        ]);
    }

    public function detailPegawai(int $id): Response
    {
        $pegawai = Employee::with(['penugasan.jabatan', 'penugasan.lembaga', 'sertifikasi'])->findOrFail($id);

        return Inertia::render('Kepegawaian/DetailPegawai', [
            'navigation' => $this->nav(),
            'pegawai' => [
                'id' => $pegawai->id,
                'nama' => $pegawai->nama,
                'nip' => $pegawai->nip,
                'nik' => $pegawai->nik,
                'jabatan' => $pegawai->jabatanAktif()?->nama ?? $pegawai->jabatan,
                'status_kepegawaian' => Employee::labelStatusKepegawaian($pegawai->status_kepegawaian),
                'aktif' => $pegawai->aktif,
                'tanggal_masuk' => $pegawai->tanggal_masuk?->translatedFormat('d M Y'),
                'tanggal_selesai' => $pegawai->tanggal_selesai?->translatedFormat('d M Y'),
                'pendidikan_terakhir' => $pegawai->pendidikan_terakhir,
            ],
            'penugasan' => $pegawai->penugasan
                ->map(fn ($p): array => [
                    'jabatan' => $p->jabatan?->nama,
                    'lembaga' => $p->lembaga?->nama,
                    'nomor_sk' => $p->nomor_sk,
                    'tanggal_mulai' => $p->tanggal_mulai?->translatedFormat('d M Y'),
                    'aktif' => $p->tanggal_selesai === null,
                ])
                ->values(),
        ]);
    }

    public function absensi(): Response
    {
        $hariIni = PresensiStaf::with('employee')
            ->whereDate('tanggal', now()->toDateString())
            ->get();

        return Inertia::render('Kepegawaian/Absensi', [
            'navigation' => $this->nav(),
            'ringkasan' => [
                'hadir' => $hariIni->where('status', 'hadir')->count(),
                'izin' => $hariIni->where('status', 'izin')->count(),
                'alpa' => $hariIni->where('status', 'alpa')->count(),
            ],
            'absensi' => $hariIni
                ->map(fn (PresensiStaf $p): array => [
                    'nama' => $p->employee?->nama ?? '—',
                    'jabatan' => $p->employee?->jabatanAktif()?->nama ?? $p->employee?->jabatan,
                    'status' => $p->status,
                    'jam' => $p->jam_masuk ?? '—',
                ])
                ->values(),
        ]);
    }

    public function slip(): Response
    {
        $periode = SlipGaji::query()->max('periode');

        $slip = SlipGaji::with('ptk')
            ->when($periode, fn ($q) => $q->where('periode', $periode))
            ->orderByDesc('id')
            ->get()
            ->map(fn (SlipGaji $s): array => [
                'id' => $s->id,
                'nama' => $s->ptk?->name ?? '—',
                'status' => $s->status,
                'bruto' => (float) $s->bruto,
                'tunjangan' => (float) $s->tunjangan,
                'potongan' => (float) ($s->potongan_cuti + $s->potongan_kasbon + $s->pph21_dipotong + $s->bpjs_dipotong),
                'neto' => (float) $s->neto,
            ])
            ->values();

        return Inertia::render('Kepegawaian/Slip', [
            'navigation' => $this->nav(),
            'periode' => $periode,
            'slip' => $slip,
        ]);
    }

    public function cuti(): Response
    {
        $pengajuan = Cuti::with('employee')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (Cuti $c): array => [
                'id' => $c->id,
                'nama' => $c->employee?->nama ?? '—',
                'jabatan' => $c->employee?->jabatanAktif()?->nama ?? $c->employee?->jabatan,
                'jenis' => Cuti::JENIS[$c->jenis] ?? $c->jenis,
                'tanggal_mulai' => $c->tanggal_mulai?->translatedFormat('d M Y'),
                'tanggal_selesai' => $c->tanggal_selesai?->translatedFormat('d M Y'),
                'durasi' => $c->jumlah_hari,
                'alasan' => $c->alasan,
                'status' => $c->status,
            ])
            ->values();

        return Inertia::render('Kepegawaian/Cuti', [
            'navigation' => $this->nav(),
            'pengajuan' => $pengajuan,
        ]);
    }
}
