<?php

namespace Modules\Keuangan\Filament\Resources\Tagihan\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Keuangan\Filament\Resources\Tagihan\TagihanResource;

class EditTagihan extends EditRecord
{
    protected static string $resource = TagihanResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $nominal = (float) ($data['nominal'] ?? 0);
        $diskon = (float) ($data['diskon'] ?? 0);
        $data['nominal_akhir'] = max($nominal - $diskon, 0);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
