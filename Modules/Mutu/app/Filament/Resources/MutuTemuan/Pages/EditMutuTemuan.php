<?php

namespace Modules\Mutu\Filament\Resources\MutuTemuan\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Mutu\Filament\Resources\MutuTemuanResource;

class EditMutuTemuan extends EditRecord
{
    protected static string $resource = MutuTemuanResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
