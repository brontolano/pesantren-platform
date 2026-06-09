<?php

namespace App\Filament\Resources\Approval\ApprovalRule\Pages;

use App\Filament\Resources\Approval\ApprovalRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApprovalRule extends ListRecords
{
    protected static string $resource = ApprovalRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
