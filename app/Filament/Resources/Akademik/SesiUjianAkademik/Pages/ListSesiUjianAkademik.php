<?php

namespace App\Filament\Resources\Akademik\SesiUjianAkademik\Pages;

use App\Filament\Resources\Akademik\SesiUjianAkademik\SesiUjianAkademikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSesiUjianAkademik extends ListRecords
{
    protected static string $resource = SesiUjianAkademikResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
