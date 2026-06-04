<?php

namespace App\Filament\Resources\Santri\Pages;

use App\Filament\Exports\SantriExporter;
use App\Filament\Resources\Santri\SantriResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Modules\Kesantrian\Models\Kelas;

class ListSantri extends ListRecords
{
    protected static string $resource = SantriResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(SantriExporter::class)
                ->label('Export Santri')
                ->icon('heroicon-o-arrow-down-tray'),
            Action::make('exportCsvPerKelas')
                ->label('Export Excel per Kelas')
                ->icon('heroicon-o-table-cells')
                ->form([
                    Select::make('kelas_id')
                        ->label('Kelas')
                        ->options(fn () => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id'))
                        ->required()
                        ->searchable(),
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            '' => 'Semua status',
                            'aktif' => 'Aktif',
                            'cuti' => 'Cuti',
                            'lulus' => 'Lulus',
                            'keluar' => 'Keluar',
                            'dikeluarkan' => 'Dikeluarkan',
                        ])
                        ->default(''),
                ])
                ->action(fn (array $data) => redirect()->route('tenant.kesantrian.santri.export.csv', $data)),
            Action::make('printPdfPerKelas')
                ->label('Cetak / Simpan PDF')
                ->icon('heroicon-o-printer')
                ->form([
                    Select::make('kelas_id')
                        ->label('Kelas')
                        ->options(fn () => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id'))
                        ->required()
                        ->searchable(),
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            '' => 'Semua status',
                            'aktif' => 'Aktif',
                            'cuti' => 'Cuti',
                            'lulus' => 'Lulus',
                            'keluar' => 'Keluar',
                            'dikeluarkan' => 'Dikeluarkan',
                        ])
                        ->default(''),
                ])
                ->action(fn (array $data) => redirect()->route('tenant.kesantrian.santri.export.print', $data))
                ->openUrlInNewTab(false),
            CreateAction::make(),
        ];
    }
}

