<?php

namespace Modules\PPDB\Filament\Resources\SoalUjian\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\PPDB\Filament\Resources\SoalUjianResource;

class EditSoalUjian extends EditRecord
{
    protected static string $resource = SoalUjianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
