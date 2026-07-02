<?php

namespace Modules\Akademik\Filament\Resources\SetoranSorogan\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Akademik\Filament\Resources\SetoranSorogan\SetoranSoroganResource;

class EditSetoranSorogan extends EditRecord
{
    protected static string $resource = SetoranSoroganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
