<?php

namespace Modules\Akademik\Filament\Resources\SetoranSorogan\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Filament\Resources\SetoranSorogan\SetoranSoroganResource;

class ListSetoranSorogan extends ListRecords
{
    protected static string $resource = SetoranSoroganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('progres')
                ->label('Progres per Santri')
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->url(fn (): string => SetoranSoroganResource::getUrl('progres')),
        ];
    }
}
