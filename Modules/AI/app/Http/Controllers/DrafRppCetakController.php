<?php

namespace Modules\AI\Http\Controllers;

use App\Models\User;
use App\Services\ModuleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\AI\Models\AiDrafRpp;

/**
 * T-A041 — lembar cetak & unduh dokumen draf RPP (target _blank / unduhan
 * dari halaman GeneratorRpp). WAJIB login + konteks tenant + permission
 * `ai.generator.rpp` + modul `ai` tampil — pagar sama dengan halamannya.
 *
 * KEPUTUSAN EKSPOR (T-A041): phpoffice/phpword TIDAK ada di vendor dan
 * dependency baru tidak ditambahkan — `unduh` mengirim HTML ber-MIME Word
 * (application/vnd.ms-word, trik kompatibilitas Word lama: Word membuka
 * HTML sebagai dokumen). KETERBATASAN: bukan OOXML/.docx sungguhan — Word
 * dapat menampilkan peringatan format & layout sederhana; bila phpword
 * kelak tersedia, ganti badan unduh() dengan pembuatan DOCX sesungguhnya.
 */
class DrafRppCetakController extends Controller
{
    /** Lembar cetak print-friendly (toolbar tombol cetak, @media print). */
    public function cetak(Request $request, int $draf): View
    {
        $paket = $this->paketSah($request, $draf);

        return view('ai::print.draf-rpp', [
            'paket' => $paket,
            'namaPengesah' => $this->namaPengesah($paket),
            'unduh' => false,
        ]);
    }

    /** Unduhan .doc — HTML yang sama dengan lembar cetak, ber-MIME Word. */
    public function unduh(Request $request, int $draf): Response
    {
        $paket = $this->paketSah($request, $draf);

        $html = view('ai::print.draf-rpp', [
            'paket' => $paket,
            'namaPengesah' => $this->namaPengesah($paket),
            'unduh' => true,
        ])->render();

        $nama = 'rpp-'.Str::slug($paket->judul ?: 'dokumen').'-'.$paket->getKey().'.doc';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-word; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
        ]);
    }

    private function paketSah(Request $request, int $draf): AiDrafRpp
    {
        abort_unless(tenancy()->initialized, 404);

        $modulTampil = app(ModuleService::class)->isVisibleForTenant('ai', tenant());
        abort_unless($modulTampil, 404);

        abort_unless($request->user()?->can('ai.generator.rpp') ?? false, 403);

        /** @var AiDrafRpp $paket */
        $paket = AiDrafRpp::query()->findOrFail($draf);

        abort_unless(
            in_array($paket->status, [AiDrafRpp::STATUS_DRAF, AiDrafRpp::STATUS_DISAHKAN], true),
            404,
        );

        return $paket;
    }

    private function namaPengesah(AiDrafRpp $paket): ?string
    {
        return $paket->disahkan_oleh !== null
            ? User::query()->whereKey($paket->disahkan_oleh)->value('name')
            : null;
    }
}
