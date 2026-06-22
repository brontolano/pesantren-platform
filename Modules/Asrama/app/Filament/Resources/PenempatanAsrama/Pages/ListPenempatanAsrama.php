<?php

namespace Modules\Asrama\Filament\Resources\PenempatanAsrama\Pages;

use Modules\Asrama\Filament\Resources\PenempatanAsrama\PenempatanAsramaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Modules\Asrama\Services\PenempatanOtomatisService;

class ListPenempatanAsrama extends ListRecords
{
    protected static string $resource = PenempatanAsramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('penempatan_otomatis')
                ->label('Penempatan Otomatis')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->modalDescription('Menempatkan santri yang BELUM punya kamar aktif ke kamar cocok (gender + kapasitas + seangkatan). Idempoten — re-run aman.')
                ->schema([
                    TextInput::make('tahun_angkatan')->label('Tahun Angkatan (opsional)')->numeric(),
                    Select::make('gender')->label('Gender (opsional)')->native(false)
                        ->options(['putra' => 'Putra', 'putri' => 'Putri']),
                ])
                ->action(function (array $data): void {
                    $opts = [];
                    if (! empty($data['tahun_angkatan'])) {
                        $opts['tahun_angkatan'] = (int) $data['tahun_angkatan'];
                    }
                    if (! empty($data['gender'])) {
                        $opts['gender'] = $data['gender'];
                    }
                    $hasil = app(PenempatanOtomatisService::class)->tempatkanOtomatis($opts);
                    Notification::make()
                        ->title("{$hasil['ditempatkan']} santri ditempatkan, {$hasil['gagal']} tanpa kamar")
                        ->success()->send();
                }),
        ];
    }
}

