<?php

namespace Modules\Tahfidz\Filament\Resources\Munaqasyah\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Tahfidz\Filament\Resources\Munaqasyah\MunaqasyahResource;

class ListMunaqasyah extends ListRecords
{
    protected static string $resource = MunaqasyahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Catat Munaqasyah'),
            // WP-11 — cetak daftar peserta wisuda per periode (lulus + ditandai).
            Action::make('cetakDaftarWisuda')
                ->label('Cetak Daftar Wisuda')
                ->icon('heroicon-o-printer')
                ->form([
                    Select::make('periode_akademik_id')
                        ->label('Periode Akademik')
                        ->options(fn () => PeriodeAkademik::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id'))
                        ->required()
                        ->searchable(),
                ])
                ->action(fn (array $data) => redirect()->to(
                    route('tenant.tahfidz.export.wisuda.print', ['periode_akademik_id' => $data['periode_akademik_id']]),
                )),
        ];
    }
}
