<?php

namespace Modules\Akademik\Filament\Resources\Rubrik\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Akademik\Filament\Resources\Rubrik\RubrikKriteriaResource;

class EditRubrikKriteria extends EditRecord
{
    protected static string $resource = RubrikKriteriaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
