<?php

namespace Modules\Kesantrian\Filament\Resources\PelanggaranSantri\Pages;

use Modules\Kesantrian\Filament\Resources\PelanggaranSantri\PelanggaranSantriResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPelanggaranSantri extends ListRecords
{
    protected static string $resource = PelanggaranSantriResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Catat Kejadian')];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),
            'dicatat' => Tab::make('Menunggu Verifikasi')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('status', 'dicatat')),
            'diverifikasi' => Tab::make('Diverifikasi')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('status', 'diverifikasi')),
            'sanksi_dijalankan' => Tab::make('Sanksi Dijalankan')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('status', 'sanksi_dijalankan')),
            'selesai' => Tab::make('Selesai')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('status', 'selesai')),
            'dibatalkan' => Tab::make('Dibatalkan')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('status', 'dibatalkan')),
        ];
    }
}
