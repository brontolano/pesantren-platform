<?php

namespace Modules\Kesantrian\Filament\Resources\JenisPelanggaran\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Kesantrian\Filament\Resources\JenisPelanggaran\JenisPelanggaranResource;

class EditJenisPelanggaran extends EditRecord
{
    protected static string $resource = JenisPelanggaranResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
