# CMS — Peta Modul (AGENTS.md)
> Orientasi cepat. Untuk tugas di modul ini, muat: folder Modules/CMS/ + .d.ts terkait + resources/js/Components/Ui/README.md. Acuan: docs/KONVENSI.md, docs/PLAYBOOK-IRISAN.md.

**Domain:** Website/CMS multi-tenant — Artikel (blog), Galeri (album+foto), Landing page tenant (section builder), Identitas/Branding website, Embed widget & sitemap.

**Model inti:** (`Modules/CMS/app/Models/`)
- `Article` (tabel `articles`, SoftDeletes, slug+publish+SEO)
- `Gallery` (tabel `galleries`, hasMany images, cover via resolveMediaUrl)
- `GalleryImage` (tabel `gallery_images`)
- `WebsiteSection` (tabel `website_sections`, kolom JSON `config`, `section_type` hero/profile/blog/gallery/faq/testimonial/ppdb_cta)
- `WebsiteSetting` (tabel `website_settings`, key/value JSON; `contact`, `seo`)

**Service/Event inti:** `Services/TenantWebsiteService` (buildLandingPagePayload + buildArticleSeoPayload, normalisasi tiap section, deteksi tenant albakids). `MediaRegistrar` (App\Services) auto-index cover/foto via hook `booted()` di Gallery/GalleryImage. EventServiceProvider kosong (`$listen=[]`).

**UI Filament:** (SEKARANG di `app/Filament/…`, target pindah `Modules/CMS/app/Filament/`) — grup nav "Kelembagaan & Komunikasi", gate `moduleVisible('cms')`:
- Resource: `Resources/CMS/Article/ArticleResource`, `Resources/CMS/Gallery/GalleryResource` (+Concerns/SyncsGalleryPhotos), `Resources/CMS/WebsiteSetting/WebsiteSettingResource`, `Resources/CMS/Profile/ProfileResource` (model=WebsiteSection, mode lanjutan)
- Page: `Pages/CMS/WebsiteBuilder`, `Pages/CMS/WebsiteIdentity`

**Endpoint/portal terkait:** landing+embed di `routes/web.php` (tenant) — `TenantLandingPageController` (`/`), `EmbedWidgetController` (`tenant.embed.script` `/embed/widget.js`, `tenant.embed.data`). Di `routes/tenant.php`: `tenant.cms.articles.show` (`/artikel/{slug}` → ArticleDetailController), `tenant.cms.sitemap` (`/sitemap.xml`). Route `cms` resource modul (`Modules/CMS/routes/web.php`+`api.php`) belum dipakai UI.

**Migrasi & seeder:** `database/migrations/2026_06_04_094500_create_cms_tables.php` (5 tabel) + `..._add_seo_to_galleries.php`; `database/seeders/CMSDatabaseSeeder` masih stub kosong.

**Jebakan modul ini:**
- File tenant: serve via route `/_tenant-storage/{path}` (lihat `Gallery::resolveMediaUrl` & `TenantWebsiteService::resolveAssetUrl`), BUKAN `Storage::url` (404 di Docker+stancl/tenancy).
- Tenancy: semua model pakai `BelongsToTenant`; DB-per-tenant fisik. Cabang khusus tenant `albakids` (section default + admission inline) di TenantWebsiteService.
- Filament v5: grup+item berikon → panel authed 500; WAJIB jalankan TenantPanelAuthedSmokeTest tiap ubah Filament CMS.

**Status rollout 5-lapisan:** Gelombang **G3**, modul **CORE** (`is_core=true`, label "Website & CMS", ModuleRegistrySeeder). BUKAN modul uang. Wajib smoke authed (ada UI Filament). Perhatian khusus media → `/_tenant-storage`.
