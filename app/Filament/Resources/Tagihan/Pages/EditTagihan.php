<?php

namespace App\Filament\Resources\Tagihan\Pages;

use App\Filament\Resources\Tagihan\TagihanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

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
