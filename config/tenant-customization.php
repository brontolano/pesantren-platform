<?php

/*
 * Registry kustomisasi per-tenant (white-label T3, Refs #343).
 *
 * Tujuan: mengganti cabang `if AlbaKidsTenant::isActive()` yang tersebar di
 * hot path dengan lookup deklaratif. Slice pertama HANYA mencakup pemilihan
 * komponen landing; kunci lain (tema, nav, panel, perilaku) akan menyusul
 * bertahap — lihat docs/INVENTARIS-KUSTOMISASI-TENANT.md.
 *
 * Struktur:
 * - 'default'  : nilai fallback untuk semua tenant tanpa entri khusus.
 * - 'tenants'  : keyed by tenant key (getTenantKey()). Tiap entri boleh punya
 *                'hosts' (alias host penuh; cocok saat tenancy belum
 *                terinisialisasi tetapi host sudah diketahui — perilaku sama
 *                dengan AlbaKidsTenant::isActive()).
 */
return [
    'default' => [
        'landing_component' => 'CMS/LandingPage',

        // Profil panel Filament tenant (set resources/pages/widgets).
        // 'default' = panel penuh; 'albakids_ringkas' = surface admin ringkas
        // (PPDB + WA saja; resource lain sengaja TIDAK terdaftar → /admin/* 404
        // BY DESIGN). Dibaca TenantPanelProvider.
        'panel_profile' => 'default',

        // Tema panel default bila tenant belum memilih via Setting
        // (App\Support\PanelTheme::SETTING_THEME). Pilihan eksplisit tenant
        // tetap menang.
        'panel_theme_default' => 'pixio',

        // Halaman '/' domain 'sistem' untuk tamu & peran jemaah:
        // 'landing' = Beranda publik CMS (fallback login bila gagal render);
        // 'login'   = langsung form login bermerek (perilaku tenant custom).
        'guest_home' => 'landing',

        // Varian form login bermerek ('default' = gaya platform/Pixio;
        // 'albakids' = gaya asli tenant custom). Sejak tahap 4, kelas body
        // guard tema dibaca langsung dari 'body_class'; kunci ini disimpan
        // untuk varian perilaku login lain ke depan.
        'login_variant' => 'default',

        // PPDB: alur custom (verifikasi pembayaran tanpa ujian/OTP/portal peserta).
        // false = alur PPDB standar penuh.
        'ppdb_custom_flow' => false,
        'ppdb_program_options' => [
            'Reguler' => 'Reguler',
            'Tahfidz' => 'Tahfidz',
            'Muadalah' => 'Muadalah',
        ],
        'ppdb_status_default' => 'pending',
        // Scope query resource (null = tanpa filter tambahan).
        'ppdb_gelombang_scope' => null,
        'ppdb_tagihan_tipe_scope' => null,

        // Notifikasi: prefix kode template yang tampil di resource (null = semua).
        'notif_template_prefix' => null,

        // Peta override label Filament (kosong = pakai label default resource).
        'labels' => [],

        // Kelas body halaman publik tenant (guard tema di blade; '' = tanpa
        // kelas). Tenant custom pra-Pixio memakai 'tenant-albakids' agar blok
        // CSS `body.tenant-albakids` (gaya asli) tetap aktif — nilai kelas
        // dipertahankan untuk kompat CSS, keputusannya dari registry.
        'body_class' => '',

        // Default tautan konten Aplikasi (Beranda & Ibadah) — dipakai bila
        // admin belum mengisi Setting per-key. Satu sumber untuk
        // AppShellController + PengaturanBranding (dulu dua konstanta duplikat).
        // Nilai default = tautan resmi asy-syifaa.com (tenant perdana).
        'app_content_links' => [
            'app_profil_pondok_url' => 'https://asy-syifaa.com/profil-pondok-pesantren',
            'app_profil_abuya_url' => 'https://asy-syifaa.com/profil-abuya',
            'app_profil_guru_url' => 'https://asy-syifaa.com/profil-guru',
            'app_struktur_url' => 'https://asy-syifaa.com/struktur-organisasi',
            'app_instagram_url' => 'https://www.instagram.com/ponpes.asysyifaa',
            'app_instagram2_url' => 'https://www.instagram.com/asysyifaapusat/',
            'app_instagram3_url' => 'https://www.instagram.com/syifaalawiyyahofficial/',
            'app_youtube_url' => 'https://www.youtube.com/@AsySyifaaTVOfficial/videos',
            'app_facebook_url' => 'https://www.facebook.com/AsySyifaaTV/live_videos',
            'app_jadwal_kajian_url' => '',
            'app_donasi_url' => '/donasi',
            'app_zakat_wakaf_url' => '',
        ],
    ],

    'tenants' => [
        'albakids' => [
            'hosts' => ['albakids.pesantren.online'],
            'landing_component' => 'CMS/AlbaKidsLandingV2',

            'panel_profile' => 'albakids_ringkas',
            'panel_theme_default' => 'bawaan',
            'guest_home' => 'login',
            'login_variant' => 'albakids',
            'body_class' => 'tenant-albakids',

            'ppdb_custom_flow' => true,
            'ppdb_program_options' => [
                'Preschool' => 'Preschool',
            ],
            'ppdb_status_default' => 'menunggu_verifikasi',
            'ppdb_gelombang_scope' => 'Landing Alba Kids',
            'ppdb_tagihan_tipe_scope' => 'pendaftaran_albakids',

            'notif_template_prefix' => 'albakids_',

            'labels' => [
                'ppdb.calon_santri.navigation' => 'Daftar Pendaftar',
                'ppdb.calon_santri.model' => 'Pendaftar Alba Kids',
                'ppdb.calon_santri.plural' => 'Daftar Pendaftar',
                'ppdb.calon_santri.nav_group' => 'PPDB Custom',
                'ppdb.tagihan.navigation' => 'Custom Form',
                'ppdb.tagihan.model' => 'Form Website Alba Kids',
                'ppdb.tagihan.plural' => 'Custom Form',
                'ppdb.tagihan.nav_group' => 'PPDB Custom',
                'ppdb.tagihan.kolom_calon_santri' => 'Nama Pendaftar',
                'notifikasi.broadcast_wa.navigation' => 'Broadcast Pesan',
                'notifikasi.broadcast_wa.model' => 'Broadcast Pesan Alba Kids',
                'notifikasi.broadcast_wa.plural' => 'Broadcast Pesan',
                'notifikasi.broadcast_wa.nav_group' => 'Notifikasi Custom',
                'notifikasi.template.navigation' => 'Template Pesan',
                'notifikasi.template.model' => 'Template Pesan Alba Kids',
                'notifikasi.template.plural' => 'Template Pesan',
                'notifikasi.template.nav_group' => 'Notifikasi Custom',
            ],
        ],
    ],
];
