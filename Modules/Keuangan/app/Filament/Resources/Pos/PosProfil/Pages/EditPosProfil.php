<?php

namespace Modules\Keuangan\Filament\Resources\Pos\PosProfil\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Keuangan\Filament\Resources\Pos\PosProfil\PosProfilResource;

class EditPosProfil extends EditRecord
{
    protected static string $resource = PosProfilResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
