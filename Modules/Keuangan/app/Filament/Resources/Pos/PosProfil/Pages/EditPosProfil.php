<?php

namespace Modules\Keuangan\Filament\Resources\Pos\PosProfil\Pages;

use Modules\Keuangan\Filament\Resources\Pos\PosProfil\PosProfilResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPosProfil extends EditRecord
{
    protected static string $resource = PosProfilResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
