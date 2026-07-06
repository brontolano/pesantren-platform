<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\Rapor;
use Modules\Akademik\Models\RaporDiniyah;
use Modules\Akademik\Services\KelulusanService;
use Modules\Kesantrian\Models\Alumni;
use Symfony\Component\HttpFoundation\Response;

class AkademikExportController extends Controller
{
    public function raporPrint(Request $request, Rapor $rapor): Response
    {
        abort_unless(auth()->user()?->canAny(['view_akademik', 'manage_akademik']), 403);

        $rapor->load([
            'santri.kelas',
            'periodeAkademik',
        ]);

        return response()->view('exports.rapor-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'rapor' => $rapor,
            'printedAt' => now(),
            'periode' => $rapor->periodeAkademik ?? PeriodeAkademik::query()->find($request->integer('periode_akademik_id')),
        ]);
    }

    /**
     * WP-10 — Cetak Rapor Diniyah. Blok yang tampil mengikuti Setting tenant
     * `akademik.rapor_diniyah_blok` (policy-first): blok dimatikan → tak tercetak.
     */
    public function raporDiniyahPrint(RaporDiniyah $raporDiniyah): Response
    {
        abort_unless(auth()->user()?->canAny(['view_akademik', 'manage_akademik']), 403);

        $raporDiniyah->load(['santri.kelas', 'periodeAkademik']);

        return response()->view('exports.rapor-diniyah-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'rapor' => $raporDiniyah,
            'periode' => $raporDiniyah->periodeAkademik,
            'blokAktif' => RaporDiniyah::blokAktif(),
            'printedAt' => now(),
        ]);
    }

    /** WP-08 — Cetak syahadah (sertifikat internal) dari template per lembaga. */
    public function syahadahPrint(Alumni $alumni, KelulusanService $service): Response
    {
        abort_unless(auth()->user()?->canAny(['view_santri', 'manage_santri']), 403);
        // Dokumen resmi tanpa nomor seri tidak boleh tercetak/beredar.
        abort_unless(filled($alumni->nomor_syahadah), 404);

        return response()->view('exports.syahadah-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'alumni' => $alumni->load(['santri', 'lembaga']),
            'isi' => $service->renderSyahadah($alumni),
        ]);
    }

    /**
     * Issue #328 — Cetak Ijazah (Muadalah / PKPPS) parameterik per jenjang
     * Ula/Wustha/Ulya. Memuat elemen wajib juknis: Garuda (placeholder aset),
     * nomor ijazah, lembaga + NSPP, nama/TTL/NIS/NISN, jenjang, tanggal
     * penerbitan, ttd pimpinan + slot stempel + slot pasfoto.
     */
    public function ijazahPrint(Request $request, Alumni $alumni): Response
    {
        abort_unless(auth()->user()?->canAny(['view_santri', 'manage_santri']), 403);
        // Dokumen resmi tanpa nomor (ijazah negara ATAU syahadah) tidak boleh beredar.
        abort_unless(filled($alumni->nomor_ijazah) || filled($alumni->nomor_syahadah), 404);

        $alumni->load(['santri', 'lembaga']);

        $jenis = $request->query('jenis', 'muadalah');
        abort_unless(in_array($jenis, ['muadalah', 'pkpps'], true), 404);

        $jenjang = strtolower((string) $request->query('jenjang', (string) $alumni->lembaga?->jenjang));
        abort_unless(in_array($jenjang, ['ula', 'wustha', 'ulya'], true), 422,
            'Jenjang lembaga belum diisi ula/wustha/ulya — lengkapi di data Lembaga atau kirim ?jenjang=.');

        return response()->view('exports.ijazah-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'alumni' => $alumni,
            'jenis' => $jenis,
            'jenjang' => $jenjang,
            'tempatTerbit' => Setting::getTenantStringValue('akademik.tempat_terbit_ijazah'),
            'namaPimpinan' => Setting::getTenantStringValue('akademik.nama_pimpinan_ijazah')
                ?: $alumni->lembaga?->kepala?->name,
            'tanggalTerbit' => $alumni->tanggal_lulus ?? now(),
        ]);
    }
}
