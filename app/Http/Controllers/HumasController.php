<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CMS\Models\Article;
use Modules\CMS\Models\Gallery;

/**
 * PWA "Humas" — persona Staf Humas/PR. Menggantikan HumasDemoController.
 *
 * KEPUTUSAN SCOPE (tak ada peran/permission "humas" tersendiri di 25-role
 * catalog — RolesAndPermissionsSeeder tak menyebut humas/publikasi sama
 * sekali; operator_divisi pun hanya punya view_dashboard/view_santri/
 * input_belanja/view_ledger, tak relevan untuk publikasi konten):
 *
 * - Backend NYATA yang dipakai: Modules\CMS\Models\Article (tabel `articles`)
 *   dan Modules\CMS\Models\Gallery + GalleryImage (tabel `galleries` +
 *   `gallery_images`) — model yang SUDAH ADA & dipakai ArticleResource /
 *   GalleryResource di panel admin (grup navigasi "Website & Konten") untuk
 *   mengelola konten website publik tenant. Tak ada model/tabel Pengumuman
 *   terpisah.
 * - SCOPE DIKURANGI: demo lama memberi label "berita"/"pengumuman"/"prestasi"
 *   per artikel berdasarkan field `category`. Field itu ADA di
 *   ArticleResource (form Select 'category') & fillable Article, TAPI TIDAK
 *   PERNAH bermigrasi jadi kolom DB nyata (lihat
 *   Modules/CMS/database/migrations/2026_06_04_094500_create_cms_tables.php
 *   — tabel `articles` tak punya kolom `category`). Nilai kategori dari form
 *   admin akan hilang senyap (Eloquent fillable tak membuat kolom). Ini bug
 *   pre-existing di luar scope PWA ini untuk diperbaiki (butuh migrasi
 *   skema). Maka semua artikel di sini ditampilkan sebagai satu kategori
 *   jujur "berita" — TIDAK memalsukan pemilahan pengumuman/prestasi yang
 *   datanya tak benar-benar tersimpan.
 * - Gate: permission `manage_santri` — permission PERTAMA dari
 *   canAny(['manage_santri','manage_tenant_settings']) yang dipakai
 *   ArticleResource::canAccess()/GalleryResource::canAccess() di panel
 *   admin. Dipilih (bukan permission baru) karena sudah dimiliki role `tu`
 *   di seeder dan konsisten dgn TuController (PR#411) yang memakai gate yang
 *   sama untuk domain administratif tanpa role khusus.
 * - Layar "Agenda" demo lama (rapat/liputan/kegiatan internal Humas) TIDAK
 *   diportal — tak ada model agenda/kalender internal nyata di sistem ini.
 *   Memaksakan akan jadi data palsu (mirip keputusan "Stok" di PosKantin
 *   PR#413). Nav-nya dihapus; hanya Beranda, Berita (list Article),
 *   Detail Artikel, dan Galeri (list Gallery + foto) yang data-nyata.
 */
class HumasController extends Controller
{
    private function nav(): array
    {
        $items = [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => '/humas', 'icon' => 'home', 'route' => 'tenant.humas.beranda'],
            ['key' => 'berita', 'label' => 'Berita', 'href' => '/humas/berita', 'icon' => 'document', 'route' => 'tenant.humas.berita'],
            ['key' => 'galeri', 'label' => 'Galeri', 'href' => '/humas/galeri', 'icon' => 'camera', 'route' => 'tenant.humas.galeri'],
        ];

        return collect($items)
            ->map(fn (array $i): array => [
                'key' => $i['key'],
                'label' => $i['label'],
                'href' => $i['href'],
                'icon' => $i['icon'],
                'active' => request()->routeIs($i['route'])
                    || ($i['key'] === 'berita' && request()->routeIs('tenant.humas.detail-artikel')),
            ])
            ->all();
    }

    private function mapArtikel(Article $a): array
    {
        return [
            'id' => $a->id,
            'judul' => $a->title,
            // 'category' bukan kolom DB nyata (lihat docblock kelas) — semua
            // artikel jujur ditampilkan sbg 'berita', bukan dipilah palsu.
            'kategori' => 'berita',
            'status' => $a->is_published ? 'published' : 'draft',
            'tanggal' => $a->published_at?->translatedFormat('d F Y') ?? $a->created_at?->translatedFormat('d F Y'),
            'ringkasan' => $a->excerpt,
            'isi' => $a->content,
            'gambar' => $a->featured_image_url ? Gallery::resolveMediaUrl($a->featured_image_url) : null,
        ];
    }

    public function beranda(): Response
    {
        $tenantId = tenant('id');

        $totalPublik = Article::where('tenant_id', $tenantId)->where('is_published', true)->count();
        $totalDraf = Article::where('tenant_id', $tenantId)->where('is_published', false)->count();
        $totalFoto = Gallery::where('tenant_id', $tenantId)->withCount('images')->get()->sum('images_count');
        $totalAlbum = Gallery::where('tenant_id', $tenantId)->count();

        $terbitTerbaru = Article::where('tenant_id', $tenantId)
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->limit(5)
            ->get()
            ->map(fn (Article $a): array => $this->mapArtikel($a));

        return Inertia::render('Humas/Beranda', [
            'userName' => Auth::user()?->name,
            'navigation' => $this->nav(),
            'stats' => [
                ['key' => 'publik', 'label' => 'Berita Publik', 'value' => $totalPublik, 'icon' => 'document', 'tone' => 'emerald', 'hint' => 'Sudah terbit'],
                ['key' => 'draft', 'label' => 'Draf', 'value' => $totalDraf, 'icon' => 'clock', 'tone' => 'amber', 'hint' => 'Belum terbit'],
                ['key' => 'foto', 'label' => 'Foto Galeri', 'value' => $totalFoto, 'icon' => 'camera', 'tone' => 'blue', 'hint' => $totalAlbum.' album'],
            ],
            'terbitTerbaru' => $terbitTerbaru,
        ]);
    }

    public function berita(): Response
    {
        $tenantId = tenant('id');

        $artikel = Article::where('tenant_id', $tenantId)
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Article $a): array => $this->mapArtikel($a));

        return Inertia::render('Humas/Berita', [
            'navigation' => $this->nav(),
            'artikel' => $artikel,
        ]);
    }

    public function detailArtikel(int $artikel): Response
    {
        $tenantId = tenant('id');

        $data = Article::where('tenant_id', $tenantId)->find($artikel);

        return Inertia::render('Humas/DetailArtikel', [
            'navigation' => $this->nav(),
            'artikel' => $data ? $this->mapArtikel($data) : null,
        ]);
    }

    public function galeri(): Response
    {
        $tenantId = tenant('id');

        $album = Gallery::where('tenant_id', $tenantId)
            ->with(['images' => fn ($q) => $q->orderBy('sort_order')])
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Gallery $g): array => [
                'id' => $g->id,
                'judul' => $g->title,
                'jumlah' => $g->images->count(),
                'tanggal' => ($g->published_at ?? $g->created_at)?->translatedFormat('d M Y'),
                'cover' => $g->cover_url,
                'foto' => $g->images->map(fn ($f): array => [
                    'id' => $f->id,
                    'caption' => $f->caption,
                    'url' => $f->url,
                ])->all(),
            ]);

        return Inertia::render('Humas/Galeri', [
            'navigation' => $this->nav(),
            'album' => $album,
        ]);
    }
}
