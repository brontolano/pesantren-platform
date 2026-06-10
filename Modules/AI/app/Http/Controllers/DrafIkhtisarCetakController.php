<?php

namespace Modules\AI\Http\Controllers;

use App\Services\ModuleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AI\Models\AiDrafIkhtisar;

/**
 * T-A042 — handout cetak print-friendly draf ikhtisar/kultum (target _blank
 * dari halaman IkhtisarKitab). WAJIB login + konteks tenant + permission
 * `ai.generator.ikhtisar` + modul `ai` tampil — pagar sama dengan halamannya.
 *
 * Ikhtisar: poin (ber-rujukan sumber) + istilah + pertanyaan diskusi +
 * daftar sumber. Kultum: kerangka + blok "PERIKSA DALIL" menonjol +
 * disclaimer wajib (PRD §7.5 + teks prosedural) — selalu bertanda DRAF
 * sebelum disahkan.
 */
class DrafIkhtisarCetakController extends Controller
{
    public function __invoke(Request $request, int $draf): View
    {
        abort_unless(tenancy()->initialized, 404);

        $modulTampil = app(ModuleService::class)->isVisibleForTenant('ai', tenant());
        abort_unless($modulTampil, 404);

        abort_unless($request->user()?->can('ai.generator.ikhtisar') ?? false, 403);

        /** @var AiDrafIkhtisar $paket */
        $paket = AiDrafIkhtisar::query()->findOrFail($draf);

        abort_unless(
            in_array($paket->status, [AiDrafIkhtisar::STATUS_DRAF, AiDrafIkhtisar::STATUS_DISAHKAN], true),
            404,
        );

        return view('ai::print.draf-ikhtisar', [
            'paket' => $paket,
            'namaPengesah' => $paket->disahkan_oleh !== null
                ? \App\Models\User::query()->whereKey($paket->disahkan_oleh)->value('name')
                : null,
        ]);
    }
}
