<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\CalonSantriDokumen;
use Modules\PPDB\Services\PpdbAdmissionDecisionService;

/**
 * PWA "PPDB Panitia" — persona panitia penerimaan santri baru. Menggantikan
 * PpdbPanitiaDemoController: data nyata via Modules\PPDB\CalonSantri +
 * CalonSantriDokumen (verifikasi berkas) + PpdbAdmissionDecisionService
 * (keputusan lulus/cadangan/ditolak — sudah ada, dipakai CalonSantriResource
 * di panel admin, termasuk notifikasi WA ke wali & kunci status pasca
 * daftar-ulang).
 *
 * Gate: middleware('auth') + can:manage_ppdb — permission yang SUDAH
 * dimiliki role `panitia_ppdb` (RolesAndPermissionsSeeder) dan sudah
 * dipakai CalonSantriResource::canAccess(), bukan permission baru.
 */
class PpdbPanitiaController extends Controller
{
    public function __construct(private PpdbAdmissionDecisionService $decisionService) {}

    private const STATUS_BELUM_DIPUTUS = ['pending', 'menunggu_verifikasi', 'verifikasi_berkas', 'cadangan'];

    private function nav(): array
    {
        $items = [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => '/ppdb-panitia', 'route' => 'beranda'],
            ['key' => 'pendaftar', 'label' => 'Pendaftar', 'href' => '/ppdb-panitia/pendaftar', 'route' => 'pendaftar'],
            ['key' => 'hasil', 'label' => 'Hasil', 'href' => '/ppdb-panitia/hasil', 'route' => 'hasil'],
        ];

        return array_map(static fn (array $i): array => [
            'key' => $i['key'],
            'label' => $i['label'],
            'href' => $i['href'],
            'active' => request()->routeIs("tenant.ppdb-panitia.{$i['route']}"),
        ], $items);
    }

    public function beranda(): Response
    {
        $gelombangList = CalonSantri::query()->distinct()->orderBy('gelombang')->pluck('gelombang')->filter()->values();

        $statistik = $gelombangList->mapWithKeys(function ($g): array {
            $base = CalonSantri::where('gelombang', $g);

            return [$g => [
                'total' => (clone $base)->count(),
                'menunggu' => (clone $base)->whereIn('status_pendaftaran', self::STATUS_BELUM_DIPUTUS)->count(),
                'lulus' => (clone $base)->where('status_pendaftaran', 'lulus')->count(),
                'ditolak' => (clone $base)->where('status_pendaftaran', 'ditolak')->count(),
            ]];
        });

        return Inertia::render('PpdbPanitia/Beranda', [
            'userName' => Auth::user()?->name,
            'gelombang' => $gelombangList->map(fn ($g): array => ['value' => $g, 'label' => "Gel. {$g}"])->values(),
            'statistik' => $statistik,
            'navigation' => $this->nav(),
        ]);
    }

    public function pendaftar(Request $request): Response
    {
        $gelombang = $request->query('gelombang');

        $query = CalonSantri::query()->with('hasilUjian');
        if ($gelombang !== null && $gelombang !== '') {
            $query->where('gelombang', $gelombang);
        }

        $pendaftar = $query->orderByDesc('created_at')->limit(200)->get()->map(fn (CalonSantri $c): array => [
            'id' => $c->id,
            'no' => $c->nomor_pendaftaran,
            'nama' => $c->nama,
            'program' => $c->program_diminati,
            'gelombang' => $c->gelombang,
            'status' => $c->status_pendaftaran,
            'asal' => $c->asal_sekolah,
            'skor' => (float) $c->hasilUjian->avg('nilai'),
        ]);

        return Inertia::render('PpdbPanitia/Pendaftar', [
            'pendaftar' => $pendaftar,
            'navigation' => $this->nav(),
        ]);
    }

    public function detail(CalonSantri $calonSantri): Response
    {
        $calonSantri->load('hasilUjian', 'dokumen');

        return Inertia::render('PpdbPanitia/Detail', [
            'calon' => [
                'id' => $calonSantri->id,
                'no' => $calonSantri->nomor_pendaftaran,
                'nama' => $calonSantri->nama,
                'status' => $calonSantri->status_pendaftaran,
                'program' => $calonSantri->program_diminati,
                'gelombang' => $calonSantri->gelombang,
                'token' => $calonSantri->tracking_token,
                'biodata' => [
                    ['label' => 'Tanggal Lahir', 'value' => optional($calonSantri->tanggal_lahir)->translatedFormat('d F Y') ?? '—'],
                    ['label' => 'Jenis Kelamin', 'value' => $calonSantri->jenis_kelamin === 'P' ? 'Perempuan' : 'Laki-laki'],
                    ['label' => 'Asal Sekolah', 'value' => $calonSantri->asal_sekolah ?? '—'],
                    ['label' => 'Nama Ayah', 'value' => $calonSantri->nama_ayah ?? '—'],
                    ['label' => 'Nama Ibu', 'value' => $calonSantri->nama_ibu ?? '—'],
                    ['label' => 'No. HP Wali', 'value' => $calonSantri->no_hp_wali ?? '—'],
                    ['label' => 'Alamat', 'value' => $calonSantri->alamat ?? '—'],
                ],
                'ujian' => $calonSantri->hasilUjian->map(fn ($u): array => [
                    'label' => $u->kategori_ujian,
                    'nilai' => (float) $u->nilai,
                    'maks' => 100,
                ]),
                'dokumen' => $calonSantri->dokumen->map(fn (CalonSantriDokumen $d): array => [
                    'nama' => $d->jenis_dokumen,
                    'status' => match ($d->effectiveStatus()) {
                        CalonSantriDokumen::STATUS_VERIFIED => 'terverifikasi',
                        CalonSantriDokumen::STATUS_REJECTED => 'ditolak',
                        default => 'menunggu',
                    },
                ]),
            ],
            'navigation' => $this->nav(),
        ]);
    }

    public function verifikasi(CalonSantri $calonSantri): Response
    {
        $calonSantri->load('dokumen');

        return Inertia::render('PpdbPanitia/Verifikasi', [
            'calon' => [
                'id' => $calonSantri->id,
                'no' => $calonSantri->nomor_pendaftaran,
                'nama' => $calonSantri->nama,
                'program' => $calonSantri->program_diminati,
                'gelombang' => $calonSantri->gelombang,
            ],
            'dokumen' => $calonSantri->dokumen->map(fn (CalonSantriDokumen $d): array => [
                'id' => $d->id,
                'nama' => $d->jenis_dokumen,
                'berkas' => $d->file_url,
                'status' => match ($d->effectiveStatus()) {
                    CalonSantriDokumen::STATUS_VERIFIED => 'terverifikasi',
                    CalonSantriDokumen::STATUS_REJECTED => 'ditolak',
                    default => 'menunggu',
                },
            ]),
            'navigation' => $this->nav(),
        ]);
    }

    public function verifikasiDokumen(Request $request, CalonSantriDokumen $dokumen): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:verified,rejected'],
            'catatan_verifikasi' => ['nullable', 'string', 'max:255'],
        ]);

        $dokumen->update([
            'status' => $data['status'],
            'verified_at' => now(),
            'verified_by' => Auth::id(),
            'catatan_verifikasi' => $data['catatan_verifikasi'] ?? null,
        ]);

        return redirect()->route('tenant.ppdb-panitia.verifikasi', $dokumen->calon_santri_id)
            ->with('success', 'Status dokumen diperbarui.');
    }

    public function keputusan(CalonSantri $calonSantri): Response
    {
        $calonSantri->load('hasilUjian', 'dokumen');

        $dokumenLengkap = $calonSantri->dokumen->isNotEmpty()
            && $calonSantri->dokumen->every(fn (CalonSantriDokumen $d): bool => $d->isVerified());

        return Inertia::render('PpdbPanitia/Keputusan', [
            'calon' => [
                'id' => $calonSantri->id,
                'no' => $calonSantri->nomor_pendaftaran,
                'nama' => $calonSantri->nama,
                'program' => $calonSantri->program_diminati,
                'gelombang' => $calonSantri->gelombang,
                'asal' => $calonSantri->asal_sekolah,
                'dokumenLengkap' => $dokumenLengkap,
                'ujian' => $calonSantri->hasilUjian->map(fn ($u): array => [
                    'label' => $u->kategori_ujian,
                    'nilai' => (float) $u->nilai,
                ]),
                'rataRata' => (float) $calonSantri->hasilUjian->avg('nilai'),
                'statusSaatIni' => $calonSantri->status_pendaftaran,
                'terkunci' => $calonSantri->santri_id !== null || $calonSantri->status_pendaftaran === 'registrasi_ulang',
            ],
            'opsiKeputusan' => [
                ['value' => 'lulus', 'label' => 'Lulus', 'tone' => 'emerald'],
                ['value' => 'cadangan', 'label' => 'Cadangan', 'tone' => 'amber'],
                ['value' => 'ditolak', 'label' => 'Ditolak', 'tone' => 'rose'],
            ],
            'navigation' => $this->nav(),
        ]);
    }

    public function simpanKeputusan(Request $request, CalonSantri $calonSantri): RedirectResponse
    {
        $data = $request->validate([
            'status_pendaftaran' => ['required', 'in:lulus,cadangan,ditolak'],
            'catatan_keputusan' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->decisionService->decide($calonSantri, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['status_pendaftaran' => $e->getMessage()]);
        }

        return redirect()->route('tenant.ppdb-panitia.hasil')->with('success', 'Keputusan tersimpan.');
    }

    public function hasil(): Response
    {
        $hasil = CalonSantri::whereIn('status_pendaftaran', ['lulus', 'cadangan', 'ditolak'])
            ->orderByDesc('tanggal_keputusan')
            ->limit(100)
            ->get()
            ->map(fn (CalonSantri $c): array => [
                'id' => $c->id,
                'no' => $c->nomor_pendaftaran,
                'nama' => $c->nama,
                'program' => $c->program_diminati,
                'keputusan' => $c->status_pendaftaran,
                'token' => $c->tracking_token,
                'tanggal' => optional($c->tanggal_keputusan)->translatedFormat('d M Y') ?? '—',
            ]);

        return Inertia::render('PpdbPanitia/Hasil', [
            'hasil' => $hasil,
            'navigation' => $this->nav(),
        ]);
    }
}
