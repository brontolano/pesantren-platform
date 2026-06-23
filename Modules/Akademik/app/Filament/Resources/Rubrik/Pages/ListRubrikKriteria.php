<?php

namespace Modules\Akademik\Filament\Resources\Rubrik\Pages;

use Modules\Akademik\Filament\Resources\Rubrik\RubrikKriteriaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRubrikKriteria extends ListRecords
{
    protected static string $resource = RubrikKriteriaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Kriteria')];
    }
}
