<?php

namespace Modules\Keuangan\Filament\Resources\Pos\PosProfil\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Keuangan\Filament\Resources\Pos\PosProfil\PosProfilResource;

class ListPosProfil extends ListRecords
{
    protected static string $resource = PosProfilResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Loket')];
    }
}
