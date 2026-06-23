<?php

namespace Modules\PPDB\Filament\Resources\GelombangPpdb\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\PPDB\Filament\Resources\GelombangPpdbResource;

class EditGelombangPpdb extends EditRecord
{
    protected static string $resource = GelombangPpdbResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
