<?php

namespace Modules\Mutu\Filament\Resources\MutuTemuan\Pages;

use Modules\Mutu\Filament\Resources\MutuTemuanResource;
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
