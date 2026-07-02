<?php

namespace Modules\Akademik\Filament\Resources\PresensiSantri\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Akademik\Filament\Resources\PresensiSantri\PresensiSantriResource;

class EditPresensiSantri extends EditRecord
{
    protected static string $resource = PresensiSantriResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
