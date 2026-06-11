<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\TenantBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Keuangan\Models\Donasi;
use Modules\Keuangan\Models\ProgramDonasi;

/**
 * 2c — Halaman donasi publik di website tenant (manual transfer, gateway
 * menunggu owner). Niat donasi disimpan 'menunggu_transfer'; donatur lalu
 * melihat instruksi rekening (Setting baitulmaal.rekening) dan mengunggah
 * bukti transfer pada halaman terima kasih (akses via client_ref UUID).
 *
 * Keamanan: rate-limit throttle:ppdb-submit (per tenant+IP), bukti transfer
 * HANYA pdf/jpg/png (anti stored-XSS — html/svg ditolak), file disimpan di
 * direktori 'donasi' yang masuk daftar sensitif /_tenant-storage (publik
 * tidak bisa melihat balik unggahan orang lain).
 */
class DonasiPublikController extends Controller
{
    public function index(): View
    {
        abort_unless(tenancy()->initialized && tenant() !== null, 404);

        return view('tenant.donasi.index', [
            'nama' => TenantBranding::name(tenant('name') ?? 'Pesantren'),
            'programs' => ProgramDonasi::query()->where('aktif', true)->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(tenancy()->initialized && tenant() !== null, 404);

        $data = $request->validate([
            'program_donasi_id' => ['required', 'integer', 'exists:program_donasi,id'],
            'nama_donatur' => ['required', 'string', 'max:150'],
            'kontak' => ['nullable', 'string', 'max:50'],
            'nominal' => ['required', 'numeric', 'min:1000', 'max:10000000000'],
            'pesan' => ['nullable', 'string', 'max:1000'],
        ]);

        $program = ProgramDonasi::query()->where('aktif', true)->findOrFail($data['program_donasi_id']);

        $donasi = Donasi::create([
            'program_donasi_id' => $program->id,
            'nama_donatur' => $data['nama_donatur'],
            'kontak' => $data['kontak'] ?? null,
            'nominal' => round((float) $data['nominal'], 2),
            'pesan' => $data['pesan'] ?? null,
            'status' => Donasi::STATUS_MENUNGGU,
            'client_ref' => (string) Str::uuid(),
        ]);

        return redirect()->route('tenant.donasi.terima-kasih', ['ref' => $donasi->client_ref]);
    }

    public function terimaKasih(string $ref): View
    {
        abort_unless(tenancy()->initialized && tenant() !== null, 404);

        $donasi = Donasi::query()->where('client_ref', $ref)->firstOrFail();

        return view('tenant.donasi.terima-kasih', [
            'nama' => TenantBranding::name(tenant('name') ?? 'Pesantren'),
            'donasi' => $donasi,
            'rekening' => Setting::getTenantStringValue('baitulmaal.rekening')
                ?: 'Hubungi bendahara pesantren untuk informasi rekening.',
        ]);
    }

    public function uploadBukti(Request $request, string $ref): RedirectResponse
    {
        abort_unless(tenancy()->initialized && tenant() !== null, 404);

        $donasi = Donasi::query()
            ->where('client_ref', $ref)
            ->where('status', Donasi::STATUS_MENUNGGU)
            ->firstOrFail();

        // Jebakan stored-XSS: WAJIB pdf/jpg/png — validasi mimes (isi file,
        // bukan sekadar ekstensi) + mimetypes ganda sebagai sabuk pengaman.
        $request->validate([
            'bukti' => [
                'required', 'file', 'max:5120',
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
            ],
        ]);

        $file = $request->file('bukti');
        $namaAman = Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs('donasi', $namaAman, 'public');

        $donasi->forceFill(['bukti_path' => $path])->save();

        return redirect()
            ->route('tenant.donasi.terima-kasih', ['ref' => $donasi->client_ref])
            ->with('bukti_sukses', true);
    }
}
