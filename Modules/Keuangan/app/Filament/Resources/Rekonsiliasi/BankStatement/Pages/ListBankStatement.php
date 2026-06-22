<?php

namespace Modules\Keuangan\Filament\Resources\Rekonsiliasi\BankStatement\Pages;

use Modules\Keuangan\Filament\Resources\Rekonsiliasi\BankStatementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBankStatement extends ListRecords
{
    protected static string $resource = BankStatementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Impor Rekening Koran')];
    }
}
