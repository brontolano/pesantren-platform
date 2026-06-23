<?php

namespace Modules\Akademik\Filament\Resources\Pelajaran\Pages;

use Modules\Akademik\Filament\Resources\Pelajaran\PelajaranResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPelajaran extends EditRecord
{
    protected static string $resource = PelajaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

