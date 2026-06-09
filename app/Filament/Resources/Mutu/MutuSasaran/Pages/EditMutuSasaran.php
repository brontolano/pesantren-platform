<?php

namespace App\Filament\Resources\Mutu\MutuSasaran\Pages;

use App\Filament\Resources\Mutu\MutuSasaranResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMutuSasaran extends EditRecord
{
    protected static string $resource = MutuSasaranResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
