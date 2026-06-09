<?php

namespace App\Filament\Resources\Kegiatan\Recurring\Pages;

use App\Filament\Resources\Kegiatan\Recurring\KegiatanRecurringResource;
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
