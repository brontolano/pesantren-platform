<?php

namespace Modules\Akademik\Filament\Resources\Program\Pages;

use Modules\Akademik\Filament\Resources\Program\ProgramStudiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProgramStudi extends EditRecord
{
    protected static string $resource = ProgramStudiResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
