<?php

namespace App\Filament\Resources\Mutu\MutuTemuan\Pages;

use App\Filament\Resources\Mutu\MutuTemuanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMutuTemuan extends EditRecord
{
    protected static string $resource = MutuTemuanResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
