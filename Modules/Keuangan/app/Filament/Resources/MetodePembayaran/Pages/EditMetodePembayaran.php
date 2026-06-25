<?php

namespace Modules\Keuangan\Filament\Resources\MetodePembayaran\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Keuangan\Filament\Resources\MetodePembayaran\MetodePembayaranResource;

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
