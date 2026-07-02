<?php

namespace Modules\PPDB\Filament\Resources\JadwalUjian\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\PPDB\Filament\Resources\JadwalUjianResource;

class EditJadwalUjian extends EditRecord
{
    protected static string $resource = JadwalUjianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
