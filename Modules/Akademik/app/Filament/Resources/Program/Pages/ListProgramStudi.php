<?php

namespace Modules\Akademik\Filament\Resources\Program\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Filament\Resources\Program\ProgramStudiResource;

class ListProgramStudi extends ListRecords
{
    protected static string $resource = ProgramStudiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Program')];
    }
}
