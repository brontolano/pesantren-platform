<?php

namespace Modules\Akademik\Filament\Resources\NgajiSesi\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Akademik\Filament\Resources\NgajiSesi\NgajiSesiResource;

class EditNgajiSesi extends EditRecord
{
    protected static string $resource = NgajiSesiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
