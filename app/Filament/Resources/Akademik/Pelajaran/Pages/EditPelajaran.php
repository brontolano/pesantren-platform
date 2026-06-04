<?php

namespace App\Filament\Resources\Akademik\Pelajaran\Pages;

use App\Filament\Resources\Akademik\Pelajaran\PelajaranResource;
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

