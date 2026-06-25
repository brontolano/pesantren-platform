<?php

namespace Modules\Kesantrian\Filament\Resources\Kelas\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Kesantrian\Filament\Resources\Kelas\KelasResource;

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
