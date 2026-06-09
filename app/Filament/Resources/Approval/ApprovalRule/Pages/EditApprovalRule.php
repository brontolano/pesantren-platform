<?php

namespace App\Filament\Resources\Approval\ApprovalRule\Pages;

use App\Filament\Resources\Approval\ApprovalRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditApprovalRule extends EditRecord
{
    protected static string $resource = ApprovalRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
