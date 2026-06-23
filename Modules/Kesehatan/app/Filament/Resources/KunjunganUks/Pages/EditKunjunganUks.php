<?php

namespace Modules\Kesehatan\Filament\Resources\KunjunganUks\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Kesehatan\Filament\Resources\KunjunganUksResource;

class EditKunjunganUks extends EditRecord
{
    protected static string $resource = KunjunganUksResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
