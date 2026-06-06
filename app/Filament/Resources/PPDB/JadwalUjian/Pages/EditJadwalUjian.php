<?php

namespace App\Filament\Resources\PPDB\JadwalUjian\Pages;

use App\Filament\Resources\PPDB\JadwalUjianResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

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
