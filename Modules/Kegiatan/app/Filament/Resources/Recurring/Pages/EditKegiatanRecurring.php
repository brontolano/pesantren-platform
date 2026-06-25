<?php

namespace Modules\Kegiatan\Filament\Resources\Recurring\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Kegiatan\Filament\Resources\Recurring\KegiatanRecurringResource;

class EditKegiatanRecurring extends EditRecord
{
    protected static string $resource = KegiatanRecurringResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
