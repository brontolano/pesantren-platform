<?php

namespace App\Support;

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
