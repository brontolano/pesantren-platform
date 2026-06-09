<?php

namespace App\Filament\Resources\Akademik\Rubrik\Pages;

use App\Filament\Resources\Akademik\Rubrik\RubrikKriteriaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRubrikKriteria extends EditRecord
{
    protected static string $resource = RubrikKriteriaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
