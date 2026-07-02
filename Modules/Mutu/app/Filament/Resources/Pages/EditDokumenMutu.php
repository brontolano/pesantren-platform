<?php

namespace Modules\Mutu\Filament\Resources\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Mutu\Filament\Resources\DokumenMutuResource;

class EditDokumenMutu extends EditRecord
{
    protected static string $resource = DokumenMutuResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
