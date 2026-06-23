<?php

namespace Modules\Kesantrian\Filament\Resources\JenisPelanggaran\Pages;

use Modules\Kesantrian\Filament\Resources\JenisPelanggaran\JenisPelanggaranResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJenisPelanggaran extends EditRecord
{
    protected static string $resource = JenisPelanggaranResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
