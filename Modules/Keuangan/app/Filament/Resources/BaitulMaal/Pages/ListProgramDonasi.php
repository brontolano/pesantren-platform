<?php

namespace Modules\Keuangan\Filament\Resources\BaitulMaal\Pages;

use Modules\Keuangan\Filament\Resources\BaitulMaal\ProgramDonasiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProgramDonasi extends ListRecords
{
    protected static string $resource = ProgramDonasiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Program')];
    }
}
