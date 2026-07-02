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
    ],

    'tenants' => [
        'albakids' => [
            'hosts' => ['albakids.pesantren.online'],
            'landing_component' => 'CMS/AlbaKidsLandingV2',

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
