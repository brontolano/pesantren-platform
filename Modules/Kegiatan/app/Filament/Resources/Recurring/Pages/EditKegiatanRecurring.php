<?php

namespace Modules\Kegiatan\Filament\Resources\Recurring\Pages;

use Modules\Kegiatan\Filament\Resources\Recurring\KegiatanRecurringResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKegiatanRecurring extends EditRecord
{
    protected static string $resource = KegiatanRecurringResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
