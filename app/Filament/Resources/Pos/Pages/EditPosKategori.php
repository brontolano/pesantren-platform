<?php

namespace App\Filament\Resources\Pos\Pages;

use App\Filament\Resources\Pos\PosKategoriResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPosKategori extends EditRecord
{
    protected static string $resource = PosKategoriResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
