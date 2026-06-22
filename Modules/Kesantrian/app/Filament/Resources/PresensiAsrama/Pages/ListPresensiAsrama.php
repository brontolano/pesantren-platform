<?php

namespace Modules\Kesantrian\Filament\Resources\PresensiAsrama\Pages;

use Modules\Kesantrian\Filament\Resources\PresensiAsrama\PresensiAsramaResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListPresensiAsrama extends ListRecords
{
    protected static string $resource = PresensiAsramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('isi_presensi')
                ->label('Isi Presensi')
                ->icon('heroicon-o-pencil-square')
                ->url(PresensiAsramaResource::getUrl('isi')),
            Action::make('rekap')
                ->label('Rekap')
                ->icon('heroicon-o-chart-bar')
                ->url(PresensiAsramaResource::getUrl('rekap'))
                ->color('gray'),
        ];
    }
}
