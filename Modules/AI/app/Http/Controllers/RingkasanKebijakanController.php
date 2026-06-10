<?php

namespace Modules\AI\Http\Controllers;

use App\Services\ModuleService;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Modules\AI\Models\AiApproval;
use Modules\AI\Services\Settings\RingkasanKebijakan;
use Modules\AI\Services\Settings\TenantSettingService;

/**
 * T-A012 — halaman ringkasan kebijakan untuk kyai/pimpinan via tautan
 * bertanda tangan (signed URL, middleware 'signed' — kedaluwarsa/dimanipulasi
 * = 403 sebelum sampai ke sini).
 *
 * BACA-SAJA secara sengaja (Hukum #2 auth): tanpa login halaman ini HANYA
 * menampilkan Ringkasan Kebijakan (template deterministik yang sama dengan
 * Wizard/halaman persetujuan) + tombol "masuk ke panel". Persetujuan tetap
 * menuntut login + permission `ai.setting.setujui` (TenantSettingService::
 * putuskan) — tidak ada aksi tulis apa pun di jalur ini.
 */
class RingkasanKebijakanController extends Controller
{
    public function __invoke(int $approval): View
    {
        // Data approval hidup di DB tenant — tautan hanya sah di domain tenant.
        abort_unless(tenancy()->initialized, 404);

        // Gerbang modul (selaras gating halaman T-A011 + gerbang gateway T-A012).
        abort_unless(
            app(ModuleService::class)->isVisibleForTenant('ai', tenant()),
            404,
        );

        /** @var AiApproval $row */
        $row = AiApproval::query()->with('setting')->findOrFail($approval);

        $payload = (array) $row->setting?->payload;

        return view('ai::ringkasan-publik', [
            'approval' => $row,
            'ringkasan' => app(RingkasanKebijakan::class)->susun($payload),
            // Anti tukar diam-diam: payload masih sama dengan saat diajukan?
            // (putuskan() tetap memverifikasi ulang — ini hanya peringatan dini.)
            'hashCocok' => $payload !== []
                && TenantSettingService::hashRingkasan($payload) === $row->ringkasan_hash,
            'urlPanel' => Route::has('filament.tenant.pages.persetujuan-ai')
                ? route('filament.tenant.pages.persetujuan-ai')
                : url('/admin/persetujuan-ai'),
            'namaPesantren' => tenant()?->name ?? 'Pesantren',
        ]);
    }
}
