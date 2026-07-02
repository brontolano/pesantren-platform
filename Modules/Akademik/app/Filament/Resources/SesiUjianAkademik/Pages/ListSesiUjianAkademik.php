<?php

namespace Modules\Akademik\Filament\Resources\SesiUjianAkademik\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Filament\Resources\SesiUjianAkademik\SesiUjianAkademikResource;

class ListSesiUjianAkademik extends ListRecords
{
    protected static string $resource = SesiUjianAkademikResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
