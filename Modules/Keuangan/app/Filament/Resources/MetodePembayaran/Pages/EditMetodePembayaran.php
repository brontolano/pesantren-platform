<?php

namespace Modules\Keuangan\Filament\Resources\MetodePembayaran\Pages;

use Modules\Keuangan\Filament\Resources\MetodePembayaran\MetodePembayaranResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMetodePembayaran extends EditRecord
{
    protected static string $resource = MetodePembayaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
