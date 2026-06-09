<?php

namespace App\Filament\Resources\Mutu\MutuInspeksi\Pages;

use App\Filament\Resources\Mutu\MutuInspeksiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMutuInspeksi extends EditRecord
{
    protected static string $resource = MutuInspeksiResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
