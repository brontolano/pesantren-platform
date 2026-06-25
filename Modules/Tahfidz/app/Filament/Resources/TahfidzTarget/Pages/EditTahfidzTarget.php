<?php

namespace Modules\Tahfidz\Filament\Resources\TahfidzTarget\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Tahfidz\Filament\Resources\TahfidzTarget\TahfidzTargetResource;

class EditTahfidzTarget extends EditRecord
{
    protected static string $resource = TahfidzTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
