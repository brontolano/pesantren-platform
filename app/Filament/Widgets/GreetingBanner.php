<?php

namespace App\Filament\Widgets;

use App\Services\PrayerTimesService;
use App\Support\TenantBranding;
use Filament\Widgets\Widget;

/**
 * Banner sambutan full-width di puncak dashboard (kedua panel).
 * "Assalamu'alaikum, {nama}" + subjudul, latar gradient emerald.
 * Sisi kanan menampilkan JADWAL SHOLAT hari ini (dihitung lokal via
 * PrayerTimesService) menggantikan ilustrasi masjid dekoratif.
 */
class GreetingBanner extends Widget
{
    protected string $view = 'filament.widgets.greeting-banner';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    protected function getViewData(): array
    {
        $name = trim((string) (auth()->user()?->name ?? 'Admin'));
        $first = $name !== '' ? explode(' ', $name)[0] : 'Admin';

        $service = app(PrayerTimesService::class);

        $prayers = $service->fivePrayers(
            TenantBranding::latitude(),
            TenantBranding::longitude(),
            TenantBranding::timezoneOffset(),
        );

        return [
            'nama'        => $first,
            'prayers'     => $prayers,
            'nextPrayer'  => $service->nextPrayerKey($prayers),
            // Koordinat fallback + zona waktu untuk JS (dipakai bila GPS ditolak).
            'lat'         => TenantBranding::latitude(),
            'lng'         => TenantBranding::longitude(),
            'tz'          => TenantBranding::timezoneOffset(),
        ];
    }
}
