<?php

namespace Modules\Kesantrian\Filament\Resources\Alumni\Pages;

use Modules\Kesantrian\Filament\Resources\Alumni\AlumniResource;
use Filament\Resources\Pages\EditRecord;

class EditAlumni extends EditRecord
{
    protected static string $resource = AlumniResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
