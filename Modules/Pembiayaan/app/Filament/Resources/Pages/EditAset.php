<?php

namespace Modules\Pembiayaan\Filament\Resources\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Pembiayaan\Filament\Resources\AsetResource;

class EditAset extends EditRecord
{
    protected static string $resource = AsetResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
