<?php

namespace Modules\Kesantrian\Filament\Resources\Kelas\Pages;

use Modules\Kesantrian\Filament\Resources\Kelas\KelasResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKelas extends EditRecord
{
    protected static string $resource = KelasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

