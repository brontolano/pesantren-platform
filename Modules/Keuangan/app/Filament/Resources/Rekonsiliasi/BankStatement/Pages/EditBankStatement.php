<?php

namespace Modules\Keuangan\Filament\Resources\Rekonsiliasi\BankStatement\Pages;

use Modules\Keuangan\Filament\Resources\Rekonsiliasi\BankStatementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBankStatement extends EditRecord
{
    protected static string $resource = BankStatementResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
