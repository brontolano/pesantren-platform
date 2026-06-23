<?php

namespace Modules\Akademik\Filament\Resources\PresensiSantri\Pages;

use Modules\Akademik\Filament\Resources\PresensiSantri\PresensiSantriResource;
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
