<?php

namespace Modules\Akademik\Filament\Resources\Program\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Akademik\Filament\Resources\Program\ProgramStudiResource;

class EditProgramStudi extends EditRecord
{
    protected static string $resource = ProgramStudiResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
