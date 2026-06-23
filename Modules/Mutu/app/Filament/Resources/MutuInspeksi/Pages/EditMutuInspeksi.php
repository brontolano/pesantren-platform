<?php

namespace Modules\Mutu\Filament\Resources\MutuInspeksi\Pages;

use Modules\Mutu\Filament\Resources\MutuInspeksiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMutuInspeksi extends EditRecord
{
    protected static string $resource = MutuInspeksiResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
