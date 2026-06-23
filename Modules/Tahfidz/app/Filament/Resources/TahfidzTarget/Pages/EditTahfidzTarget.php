<?php

namespace Modules\Tahfidz\Filament\Resources\TahfidzTarget\Pages;

use Modules\Tahfidz\Filament\Resources\TahfidzTarget\TahfidzTargetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTahfidzTarget extends EditRecord
{
    protected static string $resource = TahfidzTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
