<?php

namespace Modules\Mutu\Filament\Resources\MutuSasaran\Pages;

use Modules\Mutu\Filament\Resources\MutuSasaranResource;
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
