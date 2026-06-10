<?php

namespace App\Filament\Resources\PPDB\PaketSoal\Pages;

use App\Filament\Resources\PPDB\PaketSoal\PaketSoalResource;
use Filament\Resources\Pages\EditRecord;

class EditPaketSoal extends EditRecord
{
    protected static string $resource = PaketSoalResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\DeleteAction::make()];
    }
}
