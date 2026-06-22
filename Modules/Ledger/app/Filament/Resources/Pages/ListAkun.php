<?php

namespace Modules\Ledger\Filament\Resources\Pages;

use Modules\Ledger\Filament\Resources\AkunResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Modules\Ledger\Services\CoaTemplateService;

class ListAkun extends ListRecords
{
    protected static string $resource = AkunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('terapkan_coa_standar')
                ->label('Terapkan COA Standar')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Pastikan Bagan Akun standar pesantren tersedia (idempoten — tak menimpa saldo/akun kustom).')
                ->visible(fn (): bool => auth()->user()?->can('manage_ledger') ?? false)
                ->action(function (): void {
                    $n = app(CoaTemplateService::class)->terapkanStandar();
                    Notification::make()->title("COA standar diterapkan ({$n} akun tersedia)")->success()->send();
                }),
        ];
    }
}
