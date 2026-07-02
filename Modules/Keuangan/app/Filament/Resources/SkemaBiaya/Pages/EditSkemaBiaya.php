<?php

namespace Modules\Keuangan\Filament\Resources\SkemaBiaya\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Keuangan\Filament\Resources\SkemaBiaya\SkemaBiayaResource;

class EditSkemaBiaya extends EditRecord
{
    protected static string $resource = SkemaBiayaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
