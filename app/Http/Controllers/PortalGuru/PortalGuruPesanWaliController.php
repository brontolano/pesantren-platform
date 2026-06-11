<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalGuru;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalGuru\Concerns\ResolvesPortalGuruContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kesantrian\Models\PesanWali;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\PesanWaliService;

/**
 * F3-PWA — Kirim pesan ke wali santri (apresiasi/perhatian/info) + riwayat.
 *
 * Guru hanya boleh memilih santri kelas/halaqah BINAANNYA (pola otorisasi
 * F1/F2). Jenis 'perhatian' bisa tertahan moderasi sesuai Setting tenant.
 * Idempoten client_ref scoped guru+santri (ref orang lain → 409).
 */
class PortalGuruPesanWaliController extends Controller
{
    use ResolvesPortalGuruContext;

    public function __construct(private readonly PesanWaliService $service) {}

    public function index(Request $request): Response
    {
        $guru = $this->currentGuru($request);

        return Inertia::render('PortalGuru/PesanWali', [
            ...$this->sharedProps($request),
            'santri' => $this->service->santriBinaanQuery($guru)
                ->orderBy('nama')
                ->get()
                ->map(fn (Santri $s): array => [
                    'id' => $s->id,
                    'nis' => $s->nis,
                    'nama' => $s->nama,
                ])->values(),
            'templates' => $this->service->templates(),
            'moderasiPerhatian' => $this->service->moderasiPerhatianAktif(),
            'riwayat' => PesanWali::query()
                ->where('guru_id', $guru->id)
                ->with('santri:id,nama')
                ->latest('created_at')
                ->limit(50)
                ->get()
                ->map(fn (PesanWali $p): array => [
                    'id' => $p->id,
                    'santri' => $p->santri?->nama,
                    'jenis' => $p->jenis,
                    'isi' => $p->isi,
                    'status' => $p->status,
                    'dikirim_via' => $p->dikirim_via,
                    'alasan_tolak' => $p->alasan_tolak,
                    'sent_at' => $p->sent_at?->format('d M Y H:i'),
                    'created_at' => $p->created_at?->format('d M Y H:i'),
                ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $guru = $this->currentGuru($request);

        $data = $request->validate([
            'santri_id' => ['required', 'integer'],
            'jenis' => ['required', 'in:apresiasi,perhatian,info'],
            'isi' => ['required', 'string', 'min:5', 'max:2000'],
            'client_ref' => ['required', 'uuid'],
        ]);

        $santri = Santri::query()->findOrFail((int) $data['santri_id']);

        // Otorisasi binaan SEBELUM menulis apa pun.
        abort_unless($this->service->bolehKirimKe($guru, $santri), 403, 'Santri bukan binaan Anda.');

        $pesan = $this->service->kirim($guru, $santri, $data);

        return back()->with('success', $pesan->status === 'menunggu_moderasi'
            ? 'Pesan masuk antrean moderasi.'
            : 'Pesan terkirim ke wali.');
    }
}
