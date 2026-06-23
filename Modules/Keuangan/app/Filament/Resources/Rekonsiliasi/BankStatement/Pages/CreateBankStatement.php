<?php

namespace Modules\Keuangan\Filament\Resources\Rekonsiliasi\BankStatement\Pages;

use Modules\Keuangan\Filament\Resources\Rekonsiliasi\BankStatementResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateBankStatement extends CreateRecord
{
    protected static string $resource = BankStatementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['diimpor_oleh'] = Auth::id();
        $data['status'] = 'draft';

        return $data;
    }
}
