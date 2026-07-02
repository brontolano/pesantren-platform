<?php

namespace Modules\CMS\Services;

use App\Models\Setting;
use App\Support\TenantCustomizationRegistry;
use Illuminate\Support\Str;
use Modules\CMS\Models\Article;
use Modules\CMS\Models\Gallery;
use Modules\CMS\Models\WebsiteSection;
use Modules\CMS\Models\WebsiteSetting;

class TenantWebsiteService
{
    public function buildLandingPagePayload(): array
    {
        $tenant = tenant();
        $isAlbaKids = $this->isAlbaKidsTenant();

        $brandingName = Setting::getTenantStringValue('branding_name', $isAlbaKids ? 'Alba Kids Pre-School' : ($tenant?->name ?? 'Pesantren'));
        $brandingTagline = Setting::getTenantStringValue(
            'branding_tagline',
            $isAlbaKids
                ? 'Ruang tumbuh yang fun, aktif, imajinatif, dan positif untuk langkah awal si kecil.'
                : 'Pendidikan adab, ilmu, dan layanan santri dalam satu sistem.'
        );
        $primaryColor = Setting::getTenantStringValue('branding_primary_color', $isAlbaKids ? '#ff8a3d' : '#14532d');
        $secondaryColor = Setting::getTenantStringValue('branding_secondary_color', $isAlbaKids ? '#1f9d8f' : '#c2410c');
        $contact = WebsiteSetting::getTenantValue('contact', []);
        $seo = WebsiteSetting::getTenantValue('seo', []);
        $canonicalUrl = url('/');
        $title = $this->resolveSeoTitle(
            $seo['meta_title'] ?? null,
            $brandingName
        );
        $description = $this->resolveSeoDescription(
            $seo['meta_description'] ?? null,
            $brandingTagline
        );

        $logoUrl = $this->resolveAssetUrl(Setting::getTenantStringValue('branding_logo', null));
        // OG image landing: pakai gambar SEO khusus bila di-set editor, jika tidak
        // jatuh ke logo brand tenant (biar kartu share WA/FB tetap ada gambar).
        $ogImageUrl = $this->resolveAssetUrl($seo['image_url'] ?? null) ?? $logoUrl;
        // Favicon kini bisa berupa path file (kunci 'favicon_path') ATAU URL lama
        // (kunci 'text'). resolveAssetUrl meneruskan URL absolut apa adanya dan
        // mengubah path relatif → /_tenant-storage/{path} (kompat-mundur).
        $favicon = $this->resolveAssetUrl(Setting::getTenantStringValue('branding_favicon', null));
        $font = Setting::getTenantStringValue('branding_font', $isAlbaKids ? 'modern' : 'default');
        $fontFamily = $this->resolveFontFamily($font);

        return [
            'branding' => [
                'name' => $brandingName,
                'tagline' => $brandingTagline,
                'primaryColor' => $primaryColor,
                'secondaryColor' => $secondaryColor,
                'logoUrl' => $logoUrl,
                'favicon' => $favicon,
                'font' => $font,
                'fontFamily' => $fontFamily,
            ],
            'contact' => [
                'address' => $contact['address'] ?? 'Alamat pesantren belum diatur.',
                'phone' => $contact['phone'] ?? '',
                'email' => $contact['email'] ?? '',
            ],
            'seo' => [
                'title' => $title,
                'description' => $description,
                'canonicalUrl' => $canonicalUrl,
                'siteName' => $brandingName,
                'imageUrl' => $ogImageUrl,
                'robots' => $seo['robots'] ?? 'index,follow',
                'sitemapUrl' => url('/sitemap.xml'),
                'schema' => array_filter([
                    '@context' => 'https://schema.org',
                    '@type' => 'EducationalOrganization',
                    'name' => $brandingName,
                    'description' => $description,
                    'url' => $canonicalUrl,
                    // Logo brand → dipakai Google Knowledge Panel & kartu share.
                    'logo' => $logoUrl,
                    'image' => $ogImageUrl,
                    'telephone' => $contact['phone'] ?? null,
                    'email' => $contact['email'] ?? null,
                    'address' => array_filter([
                        '@type' => 'PostalAddress',
                        'streetAddress' => $contact['address'] ?? null,
                    ], fn ($v) => $v !== null && $v !== ''),
                ], fn ($v) => $v !== null && $v !== '' && $v !== []),
            ],
            'admission' => $this->buildAdmissionPayload($isAlbaKids),
            'sections' => $this->resolveSections($brandingName, $brandingTagline),
        ];
    }

    public function buildArticleSeoPayload(Article $article): array
    {
        $website = $this->buildLandingPagePayload();
        $canonicalUrl = url('/artikel/'.$article->slug);

        // SEO Pro: relasi `seo` (seo_meta) diisi editor via panel = PALING spesifik.
        // Rantai HORMAT nilai manual — plugin sbagai augment/fallback, BUKAN override:
        //   judul     : seo.title       → meta_title (lama) → judul artikel
        //   deskripsi : seo.description  → meta_description  → excerpt → deskripsi situs
        //   gambar OG : seo.og_image    → featured_image     → OG situs (logo)
        // Guard method_exists: bila trait/relasi belum ada (DB tenant lama belum
        // migrasi seo_meta), tetap jalan tanpa 500.
        $seoMeta = method_exists($article, 'seo') ? $article->seo : null;

        $title = $this->resolveSeoTitle(
            ($seoMeta?->title ?: null) ?: ($article->meta_title ?: $article->title),
            $website['branding']['name']
        );
        $description = $this->resolveSeoDescription(
            ($seoMeta?->description ?: null) ?: ($article->meta_description ?: $article->excerpt),
            $website['seo']['description']
        );

        $ogImage = $this->resolveAssetUrl($seoMeta?->og_image ?: null)
            ?? $this->resolveAssetUrl($article->featured_image_url ?: null)
            ?? $website['seo']['imageUrl'];

        $robots = $seoMeta?->robots ?: 'index,follow';
        $schemaType = $seoMeta?->schema_type ?: 'BlogPosting';
        $canonical = $seoMeta?->canonical_url ?: $canonicalUrl;

        return [
            'title' => $title,
            'description' => $description,
            'canonicalUrl' => $canonical,
            'siteName' => $website['branding']['name'],
            'imageUrl' => $ogImage,
            'robots' => $robots,
            'schema' => array_filter([
                '@context' => 'https://schema.org',
                '@type' => $schemaType,
                'headline' => $article->title,
                'description' => $description,
                'url' => $canonical,
                'datePublished' => optional($article->published_at)?->toIso8601String(),
                'dateModified' => optional($article->updated_at)?->toIso8601String(),
                'image' => $ogImage ? [$ogImage] : [],
                'publisher' => array_filter([
                    '@type' => 'Organization',
                    'name' => $website['branding']['name'],
                    'logo' => $website['branding']['logoUrl'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''),
            ], fn ($v) => $v !== null && $v !== '' && $v !== []),
        ];
    }

    protected function resolveSections(string $brandingName, string $brandingTagline): array
    {
        if ($this->isAlbaKidsTenant() && ! WebsiteSection::query()->where('is_active', true)->exists()) {
            return $this->defaultAlbaKidsSections($brandingName, $brandingTagline);
        }

        $sections = WebsiteSection::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (WebsiteSection $section) => $this->normalizeSection($section))
            ->values()
            ->all();

        if ($sections !== []) {
            return $sections;
        }

        return [
            [
                'type' => 'hero',
                'order' => 1,
                'config' => [
                    'eyebrow' => 'Pesantren unggul berbasis adab',
                    'title' => $brandingName,
                    'description' => $brandingTagline,
                    'primaryCtaLabel' => 'Daftar PPDB',
                    'primaryCtaHref' => '/ppdb',
                    'secondaryCtaLabel' => 'Hubungi Pesantren',
                    'secondaryCtaHref' => '#kontak',
                    'stats' => [
                        ['label' => 'Layanan', 'value' => 'PPDB online'],
                        ['label' => 'Sistem', 'value' => 'Terpusat'],
                        ['label' => 'Akses', 'value' => 'Mobile-ready'],
                    ],
                ],
            ],
            [
                'type' => 'profile',
                'order' => 2,
                'config' => $this->normalizeProfileConfig([
                    'title' => 'Profil Singkat',
                    'about' => 'Landing page tenant ini sudah membaca konfigurasi multi-tenant dan siap diperkaya per section pada task CMS berikutnya.',
                    'vision' => 'Membentuk santri yang beradab, berilmu, dan siap melayani umat.',
                    'mission' => [
                        'Menanamkan adab dan disiplin dalam kehidupan harian santri.',
                        'Menguatkan pembelajaran diniyah, akademik, dan kepemimpinan.',
                        'Menyediakan komunikasi pesantren yang rapi dan mudah diakses wali.',
                    ],
                    'contact' => [],
                    'highlights' => [
                        'Section dapat diurutkan per tenant',
                        'Branding mengikuti pengaturan tenant',
                        'CTA PPDB langsung terhubung ke modul PPDB',
                    ],
                ]),
            ],
            [
                'type' => 'blog',
                'order' => 3,
                'config' => $this->normalizeBlogConfig([]),
            ],
            [
                'type' => 'gallery',
                'order' => 4,
                'config' => $this->normalizeGalleryConfig([]),
            ],
            [
                'type' => 'faq',
                'order' => 5,
                'config' => $this->normalizeFaqConfig([
                    'title' => 'Pertanyaan yang Sering Ditanyakan',
                    'description' => 'Jawaban singkat untuk pertanyaan umum calon wali santri sebelum mendaftar.',
                    'items' => [
                        [
                            'question' => 'Bagaimana alur pendaftaran santri baru?',
                            'answer' => 'Isi formulir PPDB online, unggah dokumen, lalu pantau status seleksi dari link tracking yang diberikan sistem.',
                        ],
                        [
                            'question' => 'Apakah pesantren menyediakan asrama?',
                            'answer' => 'Ya, informasi fasilitas dan ketentuan asrama dapat dijelaskan lebih lanjut oleh tim admisi pesantren.',
                        ],
                        [
                            'question' => 'Bagaimana menghubungi panitia PPDB?',
                            'answer' => 'Gunakan kontak pesantren pada bagian bawah halaman atau tombol hubungi yang tersedia di section hero.',
                        ],
                    ],
                ]),
            ],
            [
                'type' => 'testimonial',
                'order' => 6,
                'config' => $this->normalizeTestimonialConfig([
                    'title' => 'Sambutan Pimpinan',
                    'description' => 'Pesan pengantar singkat dari pimpinan pesantren untuk wali dan calon santri.',
                    'quote' => 'Kami membuka pintu pembinaan yang menumbuhkan adab, ilmu, dan kepemimpinan santri secara utuh.',
                    'leaderName' => 'Pimpinan Pesantren',
                    'leaderRole' => 'Mudir Aam',
                    'highlights' => [
                        'Pembinaan adab sebagai fondasi utama',
                        'Sinergi diniyah, akademik, dan kepemimpinan',
                        'Komunikasi pesantren-wali yang lebih rapi',
                    ],
                ]),
            ],
            [
                'type' => 'ppdb_cta',
                'order' => 7,
                'config' => [
                    'title' => 'Penerimaan Santri Baru',
                    'body' => 'Pendaftaran online tersedia 24 jam. Isi formulir PPDB dan pantau progres secara mandiri.',
                    'buttonLabel' => 'Buka Form PPDB',
                    'buttonHref' => '/ppdb',
                ],
            ],
        ];
    }

    protected function normalizeSection(WebsiteSection $section): array
    {
        $config = $section->config ?? [];

        if ($section->section_type === 'hero') {
            $config = $this->normalizeHeroConfig($config);
        }

        if ($section->section_type === 'profile') {
            $config = $this->normalizeProfileConfig($config);
        }

        if ($section->section_type === 'blog') {
            $config = $this->normalizeBlogConfig($config);
        }

        if ($section->section_type === 'gallery') {
            $config = $this->normalizeGalleryConfig($config);
        }

        if ($section->section_type === 'faq') {
            $config = $this->normalizeFaqConfig($config);
        }

        if ($section->section_type === 'testimonial') {
            $config = $this->normalizeTestimonialConfig($config);
        }

        if ($section->section_type === 'ppdb_cta') {
            $config = $this->normalizePpdbCtaConfig($config);
        }

        return [
            'type' => $section->section_type,
            'order' => $section->sort_order,
            'config' => $config,
        ];
    }

    protected function normalizeHeroConfig(array $config): array
    {
        return [
            'eyebrow' => $config['eyebrow'] ?? 'Pesantren unggul berbasis adab',
            'title' => $config['title'] ?? 'Membentuk generasi santri yang siap memimpin dengan ilmu dan akhlak.',
            'description' => $config['description'] ?? '',
            'imageUrl' => $config['imageUrl'] ?? null,
            'imageAlt' => $config['imageAlt'] ?? ($config['title'] ?? 'Hero pesantren'),
            'primaryCtaLabel' => $config['primaryCtaLabel'] ?? 'Daftar PPDB',
            'primaryCtaHref' => $config['primaryCtaHref'] ?? '/ppdb',
            'secondaryCtaLabel' => $config['secondaryCtaLabel'] ?? 'Hubungi Pesantren',
            'secondaryCtaHref' => $config['secondaryCtaHref'] ?? '#kontak',
            'stats' => array_values(array_map(
                fn (array $stat): array => [
                    'label' => $stat['label'] ?? 'Info',
                    'value' => $stat['value'] ?? '-',
                ],
                array_values(array_filter(
                    $config['stats'] ?? [],
                    fn ($stat): bool => is_array($stat) && ($stat['label'] ?? null) !== null && ($stat['value'] ?? null) !== null,
                )),
            )),
        ];
    }

    protected function normalizeProfileConfig(array $config): array
    {
        $contact = WebsiteSetting::getTenantValue('contact', []);

        return [
            'title' => $config['title'] ?? 'Profil Pesantren',
            'about' => $config['about'] ?? ($config['body'] ?? 'Profil pesantren belum diatur.'),
            'vision' => $config['vision'] ?? 'Visi pesantren belum diatur.',
            'mission' => array_values(array_filter(
                array_map(
                    fn ($item): ?string => is_string($item) && trim($item) !== '' ? trim($item) : null,
                    $config['mission'] ?? [],
                )
            )),
            'contact' => [
                'address' => $config['contact']['address'] ?? ($contact['address'] ?? 'Alamat pesantren belum diatur.'),
                'phone' => $config['contact']['phone'] ?? ($contact['phone'] ?? ''),
                'email' => $config['contact']['email'] ?? ($contact['email'] ?? ''),
            ],
            'highlights' => array_values(array_filter(
                array_map(
                    fn ($item): ?string => is_string($item) && trim($item) !== '' ? trim($item) : null,
                    $config['highlights'] ?? [],
                )
            )),
        ];
    }

    protected function normalizeBlogConfig(array $config): array
    {
        $articles = Article::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->limit((int) ($config['limit'] ?? 3))
            ->get()
            ->map(fn (Article $article): array => [
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt ?? '',
                'featuredImageUrl' => $article->featured_image_url,
                'publishedAt' => optional($article->published_at)?->toDateString(),
                'href' => '/artikel/'.$article->slug,
            ])
            ->all();

        return [
            'title' => $config['title'] ?? 'Artikel & Kabar Pesantren',
            'description' => $config['description'] ?? 'Ikuti kabar terbaru, kegiatan santri, dan pengumuman penting dari pesantren.',
            'emptyState' => $config['emptyState'] ?? 'Artikel akan tampil di sini setelah diterbitkan.',
            'articles' => $articles,
        ];
    }

    protected function normalizeGalleryConfig(array $config): array
    {
        $galleries = Gallery::query()
            ->with(['images' => function ($query) {
                $query->orderBy('sort_order');
            }])
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->limit((int) ($config['limit'] ?? 3))
            ->get()
            ->map(function (Gallery $gallery): array {
                $images = $gallery->images
                    ->map(fn ($image): array => [
                        'imageUrl' => $image->image_url,
                        'caption' => $image->caption ?? '',
                        'isFeatured' => (bool) $image->is_featured,
                    ])
                    ->values()
                    ->all();

                return [
                    'title' => $gallery->title,
                    'slug' => $gallery->slug,
                    'description' => $gallery->description ?? '',
                    'coverImageUrl' => $gallery->cover_image_url ?: ($images[0]['imageUrl'] ?? null),
                    'publishedAt' => optional($gallery->published_at)?->toDateString(),
                    'images' => $images,
                ];
            })
            ->all();

        return [
            'title' => $config['title'] ?? 'Galeri Kegiatan Pesantren',
            'description' => $config['description'] ?? 'Dokumentasi kegiatan, momen santri, dan suasana pembinaan di lingkungan pesantren.',
            'emptyState' => $config['emptyState'] ?? 'Galeri akan tampil di sini setelah dokumentasi dipublikasikan.',
            'galleries' => $galleries,
        ];
    }

    protected function normalizeFaqConfig(array $config): array
    {
        $items = array_values(array_filter(
            array_map(function ($item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $question = isset($item['question']) && is_string($item['question']) ? trim($item['question']) : '';
                $answer = isset($item['answer']) && is_string($item['answer']) ? trim($item['answer']) : '';

                if ($question === '' || $answer === '') {
                    return null;
                }

                return [
                    'question' => $question,
                    'answer' => $answer,
                ];
            }, $config['items'] ?? [])
        ));

        return [
            'title' => $config['title'] ?? 'Pertanyaan yang Sering Ditanyakan',
            'description' => $config['description'] ?? 'Informasi singkat untuk membantu wali memahami alur pendaftaran dan layanan pesantren.',
            'emptyState' => $config['emptyState'] ?? 'FAQ akan tampil di sini setelah tim pesantren menambahkan pertanyaan umum.',
            'items' => $items,
        ];
    }

    protected function normalizePpdbCtaConfig(array $config): array
    {
        return [
            'title' => $config['title'] ?? 'Penerimaan Santri Baru',
            'body' => $config['body'] ?? 'Pendaftaran online tersedia 24 jam. Isi formulir PPDB dan pantau progres secara mandiri.',
            'buttonLabel' => $config['buttonLabel'] ?? 'Buka Form PPDB',
            'buttonHref' => $config['buttonHref'] ?? '/ppdb',
            'helperText' => $config['helperText'] ?? 'Calon santri dapat mengisi formulir, mengunggah dokumen, dan memantau status seleksi dari tenant ini.',
        ];
    }

    protected function defaultAlbaKidsSections(string $brandingName, string $brandingTagline): array
    {
        $admission = $this->buildAdmissionPayload(true);

        return [
            [
                'type' => 'hero',
                'order' => 1,
                'config' => [
                    'eyebrow' => 'Pendaftaran '.$admission['academicYear'],
                    'title' => $brandingName,
                    'description' => $brandingTagline,
                    'primaryCtaLabel' => 'Isi Form Pendaftaran',
                    'primaryCtaHref' => '#form-pendaftaran',
                    'secondaryCtaLabel' => 'Lihat Program',
                    'secondaryCtaHref' => '#program',
                    'stats' => [
                        ['label' => 'Karakter', 'value' => 'Aktif & Positif'],
                        ['label' => 'Suasana', 'value' => 'Fun & Aman'],
                        ['label' => 'Fokus', 'value' => 'Imajinatif'],
                    ],
                ],
            ],
            [
                'type' => 'profile',
                'order' => 2,
                'config' => [
                    'title' => 'Tentang Alba Kids',
                    'about' => 'Alba Kids Pre-School menghadirkan pengalaman belajar awal yang hangat, menyenangkan, dan penuh stimulasi agar anak bertumbuh percaya diri sejak langkah pertama.',
                    'vision' => 'Menjadi ruang belajar awal yang menumbuhkan anak aktif, imajinatif, dan positif bersama keluarga.',
                    'mission' => [
                        'Mendorong eksplorasi anak melalui kegiatan bermain yang terarah.',
                        'Membangun rutinitas belajar yang aman, hangat, dan penuh semangat.',
                        'Menguatkan kolaborasi sekolah dan orang tua dalam setiap tahap tumbuh kembang anak.',
                    ],
                    'highlights' => [
                        'Pembelajaran awal yang playful dan terarah.',
                        'Lingkungan hangat bagi anak yang aktif dan imajinatif.',
                        'Komunikasi pendaftaran yang praktis untuk orang tua muda.',
                    ],
                ],
            ],
            [
                'type' => 'testimonial',
                'order' => 3,
                'config' => [
                    'title' => 'Mengapa Orang Tua Memilih Alba Kids',
                    'description' => 'Lingkungan yang ceria, aman, dan mendorong setiap anak untuk percaya diri.',
                    'quote' => 'Kami percaya masa awal sekolah harus terasa menyenangkan, penuh rasa ingin tahu, dan membangun semangat positif anak setiap hari.',
                    'leaderName' => 'Tim Alba Kids',
                    'leaderRole' => 'Early Childhood Educators',
                    'highlights' => [
                        'Aktivitas motorik, bahasa, dan sosial yang seimbang.',
                        'Pendampingan hangat untuk masa adaptasi anak.',
                        'Nuansa brand yang cerah, optimistis, dan ramah keluarga.',
                    ],
                ],
            ],
            [
                'type' => 'faq',
                'order' => 4,
                'config' => [
                    'title' => 'Informasi Pendaftaran',
                    'description' => 'Semua hal penting sebelum mengisi formulir Alba Kids.',
                    'items' => [
                        [
                            'question' => 'Bagaimana alur pendaftarannya?',
                            'answer' => 'Isi formulir di halaman ini, unggah bukti transfer, lalu panitia Alba Kids akan memverifikasi data dan menghubungi Anda melalui WhatsApp.',
                        ],
                        [
                            'question' => 'Apakah bukti transfer diunggah saat pendaftaran?',
                            'answer' => 'Ya. Form ini dirancang satu langkah agar orang tua bisa mengirim data pendaftaran dan bukti transfer sekaligus.',
                        ],
                        [
                            'question' => 'Kapan saya mendapat balasan?',
                            'answer' => 'Sistem langsung mengirim pesan terima kasih ke wali dan notifikasi ke admin setelah submit berhasil.',
                        ],
                    ],
                ],
            ],
            [
                'type' => 'ppdb_cta',
                'order' => 5,
                'config' => [
                    'title' => 'Formulir Pendaftaran Alba Kids',
                    'body' => 'Siapkan data anak, data wali, dan bukti transfer. Setelah dikirim, panitia akan memeriksa dan menghubungi Anda kembali.',
                    'buttonLabel' => 'Mulai Isi Form',
                    'buttonHref' => '#form-pendaftaran',
                    'helperText' => 'Satu form untuk pendaftaran dan upload bukti transfer.',
                ],
            ],
        ];
    }

    protected function normalizeTestimonialConfig(array $config): array
    {
        return [
            'title' => $config['title'] ?? 'Sambutan Pimpinan',
            'description' => $config['description'] ?? 'Pesan singkat dari pimpinan pesantren untuk menggambarkan arah pembinaan dan layanan utama pesantren.',
            'quote' => $config['quote'] ?? 'Pesan sambutan pimpinan akan tampil di sini setelah tenant mengatur kontennya.',
            'leaderName' => $config['leaderName'] ?? 'Pimpinan Pesantren',
            'leaderRole' => $config['leaderRole'] ?? 'Mudir Aam',
            'leaderPhotoUrl' => $config['leaderPhotoUrl'] ?? null,
            'ctaLabel' => $config['ctaLabel'] ?? 'Hubungi Pesantren',
            'ctaHref' => $config['ctaHref'] ?? '#kontak',
            'highlights' => array_values(array_filter(
                array_map(
                    fn ($item): ?string => is_string($item) && trim($item) !== '' ? trim($item) : null,
                    $config['highlights'] ?? [],
                )
            )),
        ];
    }

    protected function resolveSeoTitle(?string $preferredTitle, string $brandingName): string
    {
        $title = is_string($preferredTitle) ? trim($preferredTitle) : '';

        return $title !== '' ? $title : $brandingName;
    }

    protected function resolveSeoDescription(?string $preferredDescription, string $fallback): string
    {
        $description = is_string($preferredDescription) ? trim($preferredDescription) : '';

        return $description !== '' ? Str::limit($description, 160, '') : Str::limit($fallback, 160, '');
    }

    /**
     * Resolve URL aset branding (logo, favicon) dari path relatif ke URL yang dapat diakses.
     * Storage::disk('public')->url() TIDAK dipakai — menghasilkan APP_URL/storage/{path}
     * yang 404 di Docker karena symlink hanya ke non-tenant storage.
     * Route /_tenant-storage/{path} men-serve file langsung dari PHP dengan konteks tenant.
     */
    protected function resolveAssetUrl(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/')) {
            return $value;
        }

        return url('/_tenant-storage/'.ltrim($value, '/'));
    }

    protected function resolveFontFamily(?string $font): string
    {
        return match ($font) {
            'serif' => 'Georgia, Cambria, "Times New Roman", Times, serif',
            'modern' => '"Segoe UI", "Helvetica Neue", Arial, sans-serif',
            'mono' => '"IBM Plex Mono", "Courier New", monospace',
            default => 'ui-sans-serif, system-ui, sans-serif',
        };
    }

    protected function buildAdmissionPayload(bool $isAlbaKids): array
    {
        $enabled = $isAlbaKids || (bool) Setting::getTenantValue('admission_inline_enabled', false);

        return [
            'enabled' => $enabled,
            'endpoint' => '/daftar',
            'academicYear' => Setting::getTenantStringValue('admission_academic_year', '2026/2027') ?? '2026/2027',
            'programOptions' => $isAlbaKids ? ['Playgroup', 'TK A', 'TK B'] : ['Reguler', 'Tahfidz', 'Muadalah'],
            'classOptions' => $isAlbaKids ? ['Kelompok Bermain', 'TK A', 'TK B'] : ['Kelas 1', 'Kelas 2', 'Kelas 3'],
            'transfer' => [
                'bankName' => Setting::getTenantStringValue('admission_bank_name', 'BCA') ?? 'BCA',
                'accountNumber' => Setting::getTenantStringValue('admission_account_number', '-') ?? '-',
                'accountName' => Setting::getTenantStringValue('admission_account_name', 'Alba Kids Pre-School') ?? 'Alba Kids Pre-School',
                'nominal' => Setting::getTenantStringValue('admission_transfer_amount', '') ?? '',
                'instructions' => Setting::getTenantStringValue(
                    'admission_transfer_instructions',
                    'Transfer biaya pendaftaran, lalu unggah bukti transfer pada form ini agar panitia dapat langsung memverifikasi.'
                ) ?? '',
            ],
            'adminWhatsapp' => Setting::getTenantStringValue('admission_admin_whatsapp'),
            'successMessage' => Setting::getTenantStringValue(
                'admission_success_message',
                'Panitia Alba Kids akan menghubungi Anda melalui WhatsApp setelah verifikasi data dan bukti transfer selesai.'
            ) ?? '',
            'headline' => $isAlbaKids
                ? 'Daftarkan si kecil ke Alba Kids dengan satu form yang ringkas dan ramah orang tua.'
                : 'Form pendaftaran online siap diisi langsung dari landing page.',
            'description' => $isAlbaKids
                ? 'Isi biodata anak, data wali, dan unggah bukti transfer dalam satu langkah tanpa berpindah ke portal lain.'
                : 'Form publik ini akan menyimpan data pendaftar langsung ke ERP tenant.',
        ];
    }

    /**
     * Profil default konten website dari registry (#343 tahap 5) — perujuk
     * terakhir AlbaKidsTenant dipensiunkan. Kunci 'website_defaults'
     * ('pesantren' default | 'albakids' paket preschool); resolusi registry
     * mencakup tenant key + alias host, perilaku identik resolver lama.
     */
    protected function isAlbaKidsTenant(): bool
    {
        return TenantCustomizationRegistry::stringValue('website_defaults') === 'albakids';
    }
}
