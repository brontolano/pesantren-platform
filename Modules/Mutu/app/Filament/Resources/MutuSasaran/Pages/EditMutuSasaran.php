<?php

namespace Modules\Mutu\Filament\Resources\MutuSasaran\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Mutu\Filament\Resources\MutuSasaranResource;

class EditMutuSasaran extends EditRecord
{
    protected static string $resource = MutuSasaranResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
