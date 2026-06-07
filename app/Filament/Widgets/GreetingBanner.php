<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Banner sambutan full-width di puncak dashboard (kedua panel).
 * "Assalamu'alaikum, {nama}" + subjudul, latar gradient emerald + ilustrasi masjid.
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

        return [
            'nama' => $first,
        ];
    }
}
