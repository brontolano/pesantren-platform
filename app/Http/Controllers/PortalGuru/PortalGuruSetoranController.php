<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalGuru;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalGuru\Concerns\ResolvesPortalGuruContext;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Models\Halaqah;
use Modules\Tahfidz\Services\TahfidzService;

/**
 * F1-PWA — Setoran tahfidz: pilih halaqah-ku → santri → form
 * surah/ayat/jenis (ziyadah|murajaah)/nilai/catatan → TahfidzService::setor
 * (HafalanTahfidz selesai + PenilaianTahfidz, satu transaksi, idempoten
 * via client_ref UUID pola WP-32).
 */
class PortalGuruSetoranController extends Controller
{
    use ResolvesPortalGuruContext;

    public function __construct(
        private readonly TahfidzService $tahfidzService,
    ) {}

    public function index(Request $request): Response
    {
        $guru = $this->currentGuru($request);
        $halaqah = $this->halaqahKu($guru);

        return Inertia::render('PortalGuru/Setoran', [
            ...$this->sharedProps($request),
            'halaqah' => $halaqah->map(fn ($h): array => [
                'id' => $h->id,
                'nama' => $h->nama,
                'jenis' => $h->jenis,
                'jumlah_santri' => $h->santriAktif()->count(),
            ])->values(),
            // Guru tahfidz tanpa halaqah: boleh menyetorkan untuk semua santri aktif.
            'semuaSantri' => $this->bolehSemuaSantri($guru, $halaqah->count())
                ? $this->daftarSantriAktif()
                : null,
        ]);
    }

    public function form(Request $request, int $halaqah): Response
    {
        $guru = $this->currentGuru($request);
        $binaan = $this->resolveHalaqahMilikku($halaqah, $guru->id);

        return Inertia::render('PortalGuru/SetoranForm', [
            ...$this->sharedProps($request),
            'halaqah' => [
                'id' => $binaan->id,
                'nama' => $binaan->nama,
                'jenis' => $binaan->jenis,
            ],
            'santri' => $binaan->santriAktif()
                ->orderBy('nama')
                ->get()
                ->map(fn (Santri $s): array => [
                    'id' => $s->id,
                    'nis' => $s->nis,
                    'nama' => $s->nama,
                ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $guru = $this->currentGuru($request);

        $data = $request->validate([
            'santri_id' => ['required', 'integer'],
            'halaqah_id' => ['nullable', 'integer'],
            'surah' => ['required', 'string', 'max:100'],
            'ayat_awal' => ['nullable', 'required_with:ayat_akhir', 'integer', 'min:1', 'max:286'],
            'ayat_akhir' => ['nullable', 'integer', 'min:1', 'max:286', 'gte:ayat_awal'],
            'jenis' => ['required', 'in:ziyadah,murajaah'],
            'nilai' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'client_ref' => ['required', 'uuid'],
        ]);

        $santri = Santri::query()->findOrFail((int) $data['santri_id']);

        if (! empty($data['halaqah_id'])) {
            // Lewat halaqah: wajib halaqah binaanku DAN santri anggota aktifnya.
            $binaan = $this->resolveHalaqahMilikku((int) $data['halaqah_id'], $guru->id);
            abort_unless(
                $binaan->santriAktif()->whereKey($santri->id)->exists(),
                422,
                'Santri bukan anggota aktif halaqah ini.',
            );
        } else {
            // Tanpa halaqah: hanya guru_tahfidz TANPA halaqah binaan (mode semua santri).
            abort_unless($this->bolehSemuaSantri($guru, $this->halaqahKu($guru)->count()), 403);
        }

        $this->tahfidzService->setor($santri, $data, $guru->id);

        return back()->with('success', 'Setoran tercatat.');
    }

    private function resolveHalaqahMilikku(int $halaqahId, int $guruId): Halaqah
    {
        $halaqah = Halaqah::query()->findOrFail($halaqahId);

        // Guru hanya melihat/menulis halaqah miliknya sendiri.
        abort_unless((int) $halaqah->musyrif_id === $guruId, 403);

        return $halaqah;
    }

    private function bolehSemuaSantri(User $guru, int $jumlahHalaqah): bool
    {
        return $jumlahHalaqah === 0 && $guru->hasRole('guru_tahfidz');
    }

    /** @return array<int, array{id:int, nis:?string, nama:string}> */
    private function daftarSantriAktif(): array
    {
        return Santri::query()
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get()
            ->map(fn (Santri $s): array => [
                'id' => $s->id,
                'nis' => $s->nis,
                'nama' => $s->nama,
            ])
            ->values()
            ->all();
    }
}
