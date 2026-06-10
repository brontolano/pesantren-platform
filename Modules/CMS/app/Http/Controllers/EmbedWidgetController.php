<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Modules\CMS\Models\Article;

/**
 * Widget <script> embed untuk situs eksternal (mis. WordPress lama pesantren):
 * statistik publik + berita terbaru + tombol PPDB. Hanya data PUBLIK —
 * endpoint data ber-CORS terbuka karena isinya setara halaman landing.
 *
 * Pemakaian di situs eksternal:
 *   <div id="pesantren-widget"></div>
 *   <script src="https://{tenant}.pesantren.online/embed/widget.js" defer></script>
 */
class EmbedWidgetController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        abort_unless(tenant(), 404);

        $payload = Cache::remember('embed-widget-data-'.tenant('id'), 300, function (): array {
            $santriAktif = 0;
            try {
                $santriAktif = \Modules\Kesantrian\Models\Santri::query()->where('status', 'aktif')->count();
            } catch (\Throwable) {
                // Modul Kesantrian belum dipakai tenant ini — statistik 0 saja.
            }

            $berita = Article::query()
                ->where('is_published', true)
                ->orderByDesc('published_at')
                ->limit(3)
                ->get()
                ->map(fn (Article $a): array => [
                    'judul' => (string) $a->title,
                    'url' => url('/artikel/'.$a->slug), // route publik artikel = /artikel/{slug}
                    'tanggal' => $a->published_at?->translatedFormat('d M Y'),
                ])
                ->all();

            return [
                'nama' => (string) (tenant()->name ?? 'Pesantren'),
                'statistik' => ['santri_aktif' => $santriAktif],
                'berita' => $berita,
                'ppdb_url' => url('/ppdb'),
            ];
        });

        return response()->json($payload, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function script(): Response
    {
        abort_unless(tenant(), 404);

        $dataUrl = url('/embed/data.json');

        $js = <<<JS
(function () {
    var mount = document.getElementById('pesantren-widget');
    if (!mount) { return; }

    fetch('{$dataUrl}')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var esc = function (s) {
                var div = document.createElement('div');
                div.textContent = s == null ? '' : String(s);
                return div.innerHTML;
            };

            var berita = (d.berita || []).map(function (b) {
                return '<li style="margin:4px 0"><a href="' + esc(b.url) + '" target="_blank" rel="noopener" style="color:#047857;text-decoration:none">' + esc(b.judul) + '</a>' +
                    (b.tanggal ? ' <small style="color:#78716c">(' + esc(b.tanggal) + ')</small>' : '') + '</li>';
            }).join('');

            mount.innerHTML =
                '<div style="font-family:system-ui,sans-serif;border:1px solid #e7e5e4;border-radius:16px;padding:20px;max-width:420px;background:#fff">' +
                '<div style="font-weight:700;font-size:16px;color:#1c1917">' + esc(d.nama) + '</div>' +
                '<div style="margin-top:8px;font-size:14px;color:#44403c">Santri aktif: <b>' + esc(d.statistik && d.statistik.santri_aktif) + '</b></div>' +
                (berita ? '<div style="margin-top:12px;font-size:13px;font-weight:600;color:#57534e">Berita terbaru</div><ul style="margin:4px 0 0;padding-left:18px;font-size:13px">' + berita + '</ul>' : '') +
                '<a href="' + esc(d.ppdb_url) + '" target="_blank" rel="noopener" style="display:inline-block;margin-top:14px;background:#059669;color:#fff;font-size:14px;font-weight:600;padding:10px 18px;border-radius:9999px;text-decoration:none">Daftar PPDB Online</a>' +
                '</div>';
        })
        .catch(function () { /* situs induk tetap jalan walau widget gagal */ });
})();
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
