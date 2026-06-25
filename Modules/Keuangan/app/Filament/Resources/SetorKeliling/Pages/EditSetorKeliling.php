<?php

namespace Modules\Keuangan\Filament\Resources\SetorKeliling\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Keuangan\Filament\Resources\SetorKeliling\SetorKelilingResource;

class EditSetorKeliling extends EditRecord
{
    protected static string $resource = SetorKelilingResource::class;

    protected function afterSave(): void
    {
        if ($this->record->status === 'draf') {
            $this->record->update(['total' => (float) $this->record->items()->sum('nominal')]);
        }
    }
}
