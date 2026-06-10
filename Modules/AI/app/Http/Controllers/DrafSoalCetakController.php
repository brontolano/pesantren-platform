<?php

namespace Modules\AI\Http\Controllers;

use App\Services\ModuleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AI\Models\AiDrafSoal;

/**
 * T-A040 — lembar cetak paket draf soal (print-friendly, target _blank dari
 * halaman GeneratorSoal). WAJIB login + konteks tenant + permission
 * `ai.generator.soal` + modul `ai` tampil — pagar sama dengan halamannya.
 *
 * Ekspor cetak/salin-teks adalah jalur ekspor RESMI v1 — ekspor ke modul
 * Ujian menunggu service tulis resmi bank soal (lihat TODO GeneratorSoal).
 */
class DrafSoalCetakController extends Controller
{
    public function __invoke(Request $request, int $draf): View
    {
        abort_unless(tenancy()->initialized, 404);

        $modulTampil = app(ModuleService::class)->isVisibleForTenant('ai', tenant());
        abort_unless($modulTampil, 404);

        abort_unless($request->user()?->can('ai.generator.soal') ?? false, 403);

        /** @var AiDrafSoal $paket */
        $paket = AiDrafSoal::query()->findOrFail($draf);

        abort_unless(
            in_array($paket->status, [AiDrafSoal::STATUS_DRAF, AiDrafSoal::STATUS_DISAHKAN], true),
            404,
        );

        return view('ai::print.draf-soal', [
            'paket' => $paket,
            'namaPengesah' => $paket->disahkan_oleh !== null
                ? \App\Models\User::query()->whereKey($paket->disahkan_oleh)->value('name')
                : null,
        ]);
    }
}
