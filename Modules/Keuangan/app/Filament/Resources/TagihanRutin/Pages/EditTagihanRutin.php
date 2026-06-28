<?php

namespace Modules\Keuangan\Filament\Resources\TagihanRutin\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Keuangan\Filament\Resources\TagihanRutin\TagihanRutinResource;

class EditTagihanRutin extends EditRecord
{
    protected static string $resource = TagihanRutinResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
