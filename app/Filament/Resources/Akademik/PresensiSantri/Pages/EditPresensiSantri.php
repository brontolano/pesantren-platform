<?php

namespace App\Filament\Resources\Akademik\PresensiSantri\Pages;

use App\Filament\Resources\Akademik\PresensiSantri\PresensiSantriResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPresensiSantri extends EditRecord
{
    protected static string $resource = PresensiSantriResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
