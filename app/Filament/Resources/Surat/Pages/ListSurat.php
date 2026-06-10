<?php

namespace App\Filament\Resources\Surat\Pages;

use App\Filament\Resources\Surat\SuratResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Services\SuratService;

class ListSurat extends ListRecords
{
    protected static string $resource = SuratResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('catat_surat')
                ->label('Catat Surat')
                ->icon('heroicon-o-plus')
                ->schema([
                    Select::make('jenis')
                        ->label('Jenis')
                        ->options(['masuk' => 'Surat Masuk', 'keluar' => 'Surat Keluar'])
                        ->default('masuk')
                        ->live()
                        ->required(),
                    TextInput::make('nomor_surat')
                        ->label('Nomor surat pengirim')
                        ->visible(fn ($get): bool => $get('jenis') === 'masuk')
                        ->requiredIf('jenis', 'masuk')
                        ->maxLength(120),
                    TextInput::make('kode')
                        ->label('Kode lembaga (nomor keluar)')
                        ->visible(fn ($get): bool => $get('jenis') === 'keluar')
                        ->default('PES')
                        ->maxLength(12)
                        ->helperText('Format: 001/KODE/VI/2026 — nomor urut otomatis.'),
                    DatePicker::make('tanggal_surat')->label('Tanggal surat')->default(now())->required(),
                    TextInput::make('pihak')->label('Dari / Kepada')->required()->maxLength(160),
                    TextInput::make('perihal')->label('Perihal')->required()->maxLength(200),
                    FileUpload::make('lampiran_path')
                        ->label('Lampiran (PDF/scan)')
                        ->directory('arsip-surat')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(5120),
                    Textarea::make('keterangan')->label('Keterangan')->rows(2),
                ])
                ->action(function (array $data): void {
                    $surat = app(SuratService::class)->catat($data + ['created_by' => Auth::id()]);
                    Notification::make()
                        ->title("Surat tercatat — agenda #{$surat->nomor_urut} ({$surat->nomor_surat})")
                        ->success()
                        ->send();
                }),
        ];
    }
}
