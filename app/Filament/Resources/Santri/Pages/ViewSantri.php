<?php

namespace App\Filament\Resources\Santri\Pages;

use App\Filament\Resources\Santri\SantriResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * Detail Santri (read-only) — merender infolist SantriResource, termasuk seksi
 * "Data Tambahan" dinamis (custom field per-tenant).
 */
class ViewSantri extends ViewRecord
{
    protected static string $resource = SantriResource::class;
}
