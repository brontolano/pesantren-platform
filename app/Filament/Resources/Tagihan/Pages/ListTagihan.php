<?php

namespace App\Filament\Resources\Tagihan\Pages;

use App\Filament\Resources\Tagihan\TagihanResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTagihan extends ListRecords
{
    protected static string $resource = TagihanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('export_csv')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn () => route('tenant.keuangan.export.tagihan.csv', [], false))
                ->openUrlInNewTab(),
            Action::make('export_print')
                ->label('Cetak / PDF')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('tenant.keuangan.export.tagihan.print', [], false))
                ->openUrlInNewTab(),
        ];
    }
}

