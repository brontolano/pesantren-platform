<?php

namespace Modules\PPDB\Filament\Resources\PaketSoal\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\PPDB\Filament\Resources\PaketSoal\PaketSoalResource;

class EditPaketSoal extends EditRecord
{
    protected static string $resource = PaketSoalResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
