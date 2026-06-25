<?php

namespace Modules\Akademik\Filament\Resources\KalenderAkademik\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Akademik\Filament\Resources\KalenderAkademik\KalenderAkademikResource;

class EditKalenderAkademik extends EditRecord
{
    protected static string $resource = KalenderAkademikResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
