<?php

namespace Modules\Keuangan\Filament\Resources\SetorKeliling\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Modules\Keuangan\Filament\Resources\SetorKeliling\SetorKelilingResource;

class CreateSetorKeliling extends CreateRecord
{
    protected static string $resource = SetorKelilingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['petugas_id'] = Auth::id();
        $data['status'] = 'draf';
        $data['tenant_id'] = tenant('id');

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->update(['total' => (float) $this->record->items()->sum('nominal')]);
    }
}
