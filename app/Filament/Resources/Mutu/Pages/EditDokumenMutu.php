<?php

namespace App\Filament\Resources\Mutu\Pages;

use App\Filament\Resources\Mutu\DokumenMutuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDokumenMutu extends EditRecord
{
    protected static string $resource = DokumenMutuResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
