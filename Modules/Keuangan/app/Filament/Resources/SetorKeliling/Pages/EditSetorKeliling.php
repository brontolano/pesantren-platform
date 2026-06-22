<?php

namespace Modules\Keuangan\Filament\Resources\SetorKeliling\Pages;

use Modules\Keuangan\Filament\Resources\SetorKeliling\SetorKelilingResource;
use Filament\Resources\Pages\EditRecord;

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
