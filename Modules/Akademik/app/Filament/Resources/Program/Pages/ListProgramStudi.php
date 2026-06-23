<?php

namespace Modules\Akademik\Filament\Resources\Program\Pages;

use Modules\Akademik\Filament\Resources\Program\ProgramStudiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProgramStudi extends ListRecords
{
    protected static string $resource = ProgramStudiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Program')];
    }
}
