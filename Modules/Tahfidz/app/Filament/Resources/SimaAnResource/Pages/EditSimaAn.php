<?php

namespace Modules\Tahfidz\Filament\Resources\SimaAnResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Tahfidz\Filament\Resources\SimaAnResource;

class EditSimaAn extends EditRecord
{
    protected static string $resource = SimaAnResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
