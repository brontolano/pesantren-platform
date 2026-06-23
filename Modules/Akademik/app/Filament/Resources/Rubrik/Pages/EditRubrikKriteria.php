<?php

namespace Modules\Akademik\Filament\Resources\Rubrik\Pages;

use Modules\Akademik\Filament\Resources\Rubrik\RubrikKriteriaResource;
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
