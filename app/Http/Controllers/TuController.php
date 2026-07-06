<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\StafNav;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Models\Surat;
use Modules\Core\Services\SuratService;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;

/**
 * PWA "Tata Usaha" — persona Staf TU. Menggantikan TuDemoController: data
 * nyata via Modules\Kesantrian\Santri (santri) dan Modules\Core\Surat +
 * SuratService (surat masuk/keluar, penomoran otomatis race-safe — sudah
 * ada & dipakai admin panel SuratResource sebelum PWA ini).
 *
 * Gate: middleware('auth') + can:manage_santri — permission yang SUDAH
 * dimiliki role `tu` (RolesAndPermissionsSeeder) dan sudah dipakai
 * SuratResource::canAccess() di panel admin, bukan permission baru.
 */
class TuController extends Controller
{
    public function __construct(private SuratService $suratService) {}

    private function nav(): array
    {
        // Bottom-nav STANDAR mockup (Beranda/Menu/Aktivitas/Akun) - owner 2026-07-06.
        return StafNav::items();
    }

    public function beranda(): Response
    {
        $tenantId = tenant('id');
        $santriAktif = Santri::where('tenant_id', $tenantId)->where('status', 'aktif')->count();
        $suratMasukBulanIni = Surat::where('jenis', 'masuk')
            ->whereMonth('tanggal_surat', now()->month)
            ->whereYear('tanggal_surat', now()->year)
            ->count();
        $suratKeluarBulanIni = Surat::where('jenis', 'keluar')
            ->whereMonth('tanggal_surat', now()->month)
            ->whereYear('tanggal_surat', now()->year)
            ->count();
        $totalArsip = Surat::whereNotNull('lampiran_path')->count();

        $agendaTerbaru = Surat::orderByDesc('tanggal_surat')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Surat $s): array => [
                'no' => $s->nomor_surat,
                'perihal' => $s->perihal,
                'dari' => $s->pihak,
                'arah' => $s->jenis,
                'tanggal' => $s->tanggal_surat->translatedFormat('d M Y'),
            ]);

        return Inertia::render('Tu/Beranda', [
            'userName' => Auth::user()?->name ?? 'Staf TU',
            'stats' => [
                ['key' => 'santri', 'label' => 'Santri Aktif', 'value' => (string) $santriAktif, 'icon' => 'book', 'tone' => 'emerald', 'hint' => 'Aktif saat ini'],
                ['key' => 'masuk', 'label' => 'Surat Masuk', 'value' => (string) $suratMasukBulanIni, 'icon' => 'arrow-down-left', 'tone' => 'blue', 'hint' => 'Bulan ini'],
                ['key' => 'keluar', 'label' => 'Surat Keluar', 'value' => (string) $suratKeluarBulanIni, 'icon' => 'arrow-up-right', 'tone' => 'amber', 'hint' => 'Bulan ini'],
                ['key' => 'arsip', 'label' => 'Arsip Dokumen', 'value' => (string) $totalArsip, 'icon' => 'inbox', 'tone' => 'teal', 'hint' => 'Berlampiran'],
            ],
            'pintasan' => [
                ['key' => 'santri', 'label' => 'Data Santri', 'icon' => 'book', 'tone' => 'emerald', 'href' => '/tu/santri'],
                ['key' => 'surat', 'label' => 'Buku Agenda', 'icon' => 'document', 'tone' => 'blue', 'href' => '/tu/surat'],
                ['key' => 'catat', 'label' => 'Catat Surat', 'icon' => 'plus', 'tone' => 'amber', 'href' => '/tu/catat-surat'],
                ['key' => 'arsip', 'label' => 'Arsip', 'icon' => 'inbox', 'tone' => 'teal', 'href' => '/tu/arsip'],
            ],
            'agendaTerbaru' => $agendaTerbaru,
            'navigation' => $this->nav(),
        ]);
    }

    public function santri(Request $request): Response
    {
        $tenantId = tenant('id');
        $kelas = (string) $request->query('kelas', 'semua');

        $query = Santri::where('tenant_id', $tenantId)->where('status', 'aktif')->with('kelas');
        if ($kelas !== 'semua') {
            $query->whereHas('kelas', fn ($q) => $q->where('nama_kelas', $kelas));
        }

        $daftar = $query->orderBy('nama')->limit(200)->get()->map(fn (Santri $s): array => [
            'id' => $s->id,
            'nama' => $s->nama,
            'nis' => $s->nis,
            'nisn' => $s->nisn,
            'kelas' => $s->kelas?->nama_kelas ?? '—',
        ]);

        $kelasOptions = [['value' => 'semua', 'label' => 'Semua']]
            + Kelas::where('tenant_id', $tenantId)
                ->orderBy('nama_kelas')
                ->pluck('nama_kelas')
                ->mapWithKeys(fn (string $k): array => [$k => ['value' => $k, 'label' => $k]])
                ->values()
                ->all();

        return Inertia::render('Tu/Santri', [
            'userName' => Auth::user()?->name ?? 'Staf TU',
            'kelasOptions' => array_values($kelasOptions),
            'santri' => $daftar,
            'navigation' => $this->nav(),
        ]);
    }

    public function detailSantri(Request $request): Response
    {
        $tenantId = tenant('id');
        $id = $request->query('id');
        $santri = $id
            ? Santri::where('tenant_id', $tenantId)->with('kelas')->find($id)
            : Santri::where('tenant_id', $tenantId)->where('status', 'aktif')->with('kelas')->orderBy('nama')->first();

        return Inertia::render('Tu/DetailSantri', [
            'userName' => Auth::user()?->name ?? 'Staf TU',
            'santri' => $santri ? [
                'id' => $santri->id,
                'nama' => $santri->nama,
                'nis' => $santri->nis,
                'nisn' => $santri->nisn,
                'kelas' => $santri->kelas?->nama_kelas ?? '—',
                'ttl' => trim(($santri->tempat_lahir ?? '').', '.($santri->tanggal_lahir?->translatedFormat('d F Y') ?? '')),
                'ayah' => $santri->nama_ayah ?? '—',
                'ibu' => $santri->nama_ibu ?? '—',
                'alamat' => $santri->alamat ?? '—',
            ] : null,
            'navigation' => $this->nav(),
        ]);
    }

    public function surat(): Response
    {
        $mapper = fn (Surat $s): array => [
            'id' => $s->id,
            'no' => $s->nomor_surat,
            'perihal' => $s->perihal,
            'dari' => $s->pihak,
            'jenis' => $s->keterangan ?: '—',
            'tanggal' => $s->tanggal_surat->translatedFormat('d M Y'),
        ];

        $masuk = Surat::where('jenis', 'masuk')->orderByDesc('tanggal_surat')->orderByDesc('id')->limit(50)->get()->map($mapper);
        $keluar = Surat::where('jenis', 'keluar')->orderByDesc('tanggal_surat')->orderByDesc('id')->limit(50)->get()->map($mapper);

        return Inertia::render('Tu/Surat', [
            'userName' => Auth::user()?->name ?? 'Staf TU',
            'arahOptions' => [
                ['value' => 'masuk', 'label' => 'Surat Masuk', 'icon' => 'arrow-down-left'],
                ['value' => 'keluar', 'label' => 'Surat Keluar', 'icon' => 'arrow-up-right'],
            ],
            'masuk' => $masuk,
            'keluar' => $keluar,
            'navigation' => $this->nav(),
        ]);
    }

    public function catatSurat(): Response
    {
        return Inertia::render('Tu/CatatSurat', [
            'userName' => Auth::user()?->name ?? 'Staf TU',
            'jenisOptions' => [
                ['value' => 'Keterangan', 'label' => 'Keterangan'],
                ['value' => 'Pengantar', 'label' => 'Pengantar'],
                ['value' => 'Balasan', 'label' => 'Balasan'],
                ['value' => 'Surat Tugas', 'label' => 'Surat Tugas'],
            ],
            'navigation' => $this->nav(),
        ]);
    }

    public function simpanSurat(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'jenis' => ['required', 'in:masuk,keluar'],
            'tanggal_surat' => ['required', 'date'],
            'pihak' => ['required', 'string', 'max:255'],
            'perihal' => ['required', 'string', 'max:255'],
            'nomor_surat' => ['nullable', 'string', 'max:120'],
            'keterangan' => ['nullable', 'string', 'max:120'],
        ]);

        $this->suratService->catat([
            'jenis' => $data['jenis'],
            'tanggal_surat' => $data['tanggal_surat'],
            'pihak' => $data['pihak'],
            'perihal' => $data['perihal'],
            'nomor_surat' => $data['nomor_surat'] ?? null,
            'keterangan' => $data['keterangan'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('tenant.tu.surat')->with('success', 'Surat berhasil dicatat.');
    }

    public function arsip(Request $request): Response
    {
        $kategori = (string) $request->query('kategori', 'semua');

        $query = Surat::whereNotNull('lampiran_path');
        // Kategori kasar dari kata kunci perihal — Surat tak punya kolom kategori
        // khusus; ini agar filter Arsip lama (semua/surat/sk/sertifikat) tetap
        // berfungsi tanpa migrasi baru.
        if ($kategori !== 'semua') {
            $kata = match ($kategori) {
                'sk' => 'SK',
                'sertifikat' => 'Sertifikat',
                default => null,
            };
            if ($kata !== null) {
                $query->where('perihal', 'like', "%{$kata}%");
            }
        }

        $dokumen = $query->orderByDesc('tanggal_surat')->limit(100)->get()->map(fn (Surat $s): array => [
            'id' => $s->id,
            'nama' => $s->perihal,
            'kategori' => str_contains(mb_strtolower($s->perihal), 'sk ') || str_starts_with(mb_strtolower($s->perihal), 'sk') ? 'sk'
                : (str_contains(mb_strtolower($s->perihal), 'sertifikat') ? 'sertifikat' : 'surat'),
            'nomor' => $s->nomor_surat,
            'tanggal' => $s->tanggal_surat->translatedFormat('d M Y'),
        ]);

        return Inertia::render('Tu/Arsip', [
            'userName' => Auth::user()?->name ?? 'Staf TU',
            'kategoriOptions' => [
                ['value' => 'semua', 'label' => 'Semua'],
                ['value' => 'surat', 'label' => 'Surat'],
                ['value' => 'sk', 'label' => 'SK'],
                ['value' => 'sertifikat', 'label' => 'Sertifikat'],
            ],
            'dokumen' => $dokumen,
            'navigation' => $this->nav(),
        ]);
    }
}
