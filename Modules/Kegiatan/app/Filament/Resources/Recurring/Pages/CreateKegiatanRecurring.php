<?php

namespace Modules\Kegiatan\Filament\Resources\Recurring\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Kegiatan\Filament\Resources\Recurring\KegiatanRecurringResource;

class CreateKegiatanRecurring extends CreateRecord
{
    protected static string $resource = KegiatanRecurringResource::class;
}
