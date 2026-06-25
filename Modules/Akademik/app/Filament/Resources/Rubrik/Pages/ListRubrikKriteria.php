<?php

namespace Modules\Akademik\Filament\Resources\Rubrik\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Filament\Resources\Rubrik\RubrikKriteriaResource;

class ListRubrikKriteria extends ListRecords
{
    protected static string $resource = RubrikKriteriaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Kriteria')];
    }
}
