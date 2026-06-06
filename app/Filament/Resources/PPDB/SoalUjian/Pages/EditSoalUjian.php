<?php

namespace App\Filament\Resources\PPDB\SoalUjian\Pages;

use App\Filament\Resources\PPDB\SoalUjianResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

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
