<?php

namespace Modules\Keuangan\Filament\Resources\SkemaBiaya\Pages;

use Modules\Keuangan\Filament\Resources\SkemaBiaya\SkemaBiayaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSkemaBiaya extends EditRecord
{
    protected static string $resource = SkemaBiayaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
