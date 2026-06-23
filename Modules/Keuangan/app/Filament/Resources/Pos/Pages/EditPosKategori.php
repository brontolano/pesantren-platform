<?php

namespace Modules\Keuangan\Filament\Resources\Pos\Pages;

use Modules\Keuangan\Filament\Resources\Pos\PosKategoriResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPosKategori extends EditRecord
{
    protected static string $resource = PosKategoriResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
