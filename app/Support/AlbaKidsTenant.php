<?php

namespace App\Support;

/**
 * @deprecated sejak tahap 5 #343 — NOL perujuk runtime. Semua keputusan
 * kustomisasi tenant kini lewat {@see TenantCustomizationRegistry}
 * (panelProfile(), feature(), stringValue(), label(), bodyClass(), dst.;
 * config/tenant-customization.php + override Setting 'customization.*').
 * JANGAN tambah pemanggil baru. File dipertahankan 1 rilis untuk jaring
 * pengaman (blade cache/deploy lama), lalu dihapus fisik — lihat
 * docs/INVENTARIS-KUSTOMISASI-TENANT.md #24.
 */
class AlbaKidsTenant
{
    public static function isActive(): bool
    {
        if ((string) request()?->getHost() === 'albakids.pesantren.online') {
            return true;
        }

        try {
            return (string) (tenant()?->getTenantKey() ?? '') === 'albakids';
        } catch (\Throwable) {
            return false;
        }
    }
}
