<?php

namespace Modules\Kesantrian\Filament\Resources\Alumni\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Kesantrian\Filament\Resources\Alumni\AlumniResource;

class EditAlumni extends EditRecord
{
    protected static string $resource = AlumniResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
