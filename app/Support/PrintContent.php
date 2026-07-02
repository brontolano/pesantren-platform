<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Modules\Akademik\Models\Rapor;
use Modules\Akademik\Models\RaporDiniyah;
use Modules\Core\Services\ReportExportService;
use Modules\Kesantrian\Models\Alumni;
use Modules\Kesantrian\Services\IdCardService;
use Modules\Keuangan\Models\Donasi;

/**
 * Sumber tunggal penyiapan data untuk view cetak (blade exports/*).
 *
 * Dipakai OLEH DUA jalur agar konsisten:
 *  - Route/Controller cetak lama (window.print di browser) — tetap ada sbg fallback.
 *  - Aksi Html2MediaAction di panel (render PDF rapi client-side lewat html2pdf.js).
 *
 * Tujuan: aksi PDF me-render blade + data yang SAMA PERSIS dengan route cetak,
 * tanpa menduplikasi logika load relasi / Setting di banyak tempat. Otorisasi
 * TIDAK dikerjakan di sini — pemanggil (controller pakai abort_unless, aksi
 * Filament pakai ->visible()) yang menjaga izin, sama seperti sebelumnya.
 */
class PrintContent
{
    public static function ijazah(Alumni $alumni, string $jenis, ?string $jenjang = null): View
    {
        $alumni->loadMissing(['santri', 'lembaga']);

        $jenjang = strtolower($jenjang ?: (string) $alumni->lembaga?->jenjang);

        return view('exports.ijazah-print', [
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

    public static function syahadah(Alumni $alumni, string $isi): View
    {
        return view('exports.syahadah-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'alumni' => $alumni->loadMissing(['santri', 'lembaga']),
            'isi' => $isi,
        ]);
    }

    public static function rapor(Rapor $rapor): View
    {
        $rapor->loadMissing(['santri.kelas', 'periodeAkademik']);

        return view('exports.rapor-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'rapor' => $rapor,
            'printedAt' => now(),
            'periode' => $rapor->periodeAkademik,
        ]);
    }

    public static function raporDiniyah(RaporDiniyah $rapor): View
    {
        $rapor->loadMissing(['santri.kelas', 'periodeAkademik']);

        return view('exports.rapor-diniyah-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'rapor' => $rapor,
            'periode' => $rapor->periodeAkademik,
            'blokAktif' => RaporDiniyah::blokAktif(),
            'printedAt' => now(),
        ]);
    }

    public static function kwitansiDonasi(Donasi $donasi): View
    {
        return view('exports.kuitansi-donasi-print', [
            'donasi' => $donasi->loadMissing(['program.sumberDana', 'konfirmator']),
            'namaPesantren' => TenantBranding::name(tenant('name') ?? 'Pesantren'),
        ]);
    }

    /**
     * ID Card memakai ReportExportService::html() agar kop brand tenant (nama,
     * logo, warna) ikut ter-render — sama seperti jalur unduh PDF/HTML existing.
     */
    public static function idCard(Collection $santri): Htmlable
    {
        $html = app(ReportExportService::class)->html('id-card-santri', [
            'judul' => 'ID Card Santri',
            'kartu' => app(IdCardService::class)->kartu($santri),
        ]);

        return new HtmlString($html);
    }
}
