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
    ],

    'tenants' => [
        'albakids' => [
            'hosts' => ['albakids.pesantren.online'],
            'landing_component' => 'CMS/AlbaKidsLandingV2',
        ],
    ],
];
