<?php

namespace Modules\Keuangan\Filament\Resources\TagihanRutin\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Keuangan\Filament\Resources\TagihanRutin\TagihanRutinResource;

class EditTagihanRutin extends EditRecord
{
    protected static string $resource = TagihanRutinResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** #332: nominal = total komponen bila rincian diisi. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TagihanRutinResource::sinkronkanKomponen($data);
    }
}
