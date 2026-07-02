<?php

namespace Modules\Kesantrian\Filament\Resources\Kelas\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Models\Lembaga;
use Modules\Kesantrian\Filament\Resources\Kelas\KelasResource;
use Modules\Kesantrian\Services\KesetaraanTemplateService;

class ListKelas extends ListRecords
{
    protected static string $resource = KelasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('terapkanTemplateKesetaraan')
                ->label('Terapkan Template Kesetaraan')
                ->icon('heroicon-o-academic-cap')
                ->color('gray')
                ->visible(fn (): bool => KelasResource::canCreate())
                ->form([
                    Select::make('lembaga_id')
                        ->label('Lembaga')
                        ->helperText('Kosong = kelas tanpa lembaga (lembaga default tenant). Bila jenjang lembaga sudah ula/wustha/ulya, kelas dipetakan ke jenjang itu tingkat 1..N; selain itu berurutan Ula 1-6 → Wustha 1-3 → Ulya 1-3.')
                        ->options(fn () => Lembaga::query()->where('aktif', true)->pluck('nama', 'id'))
                        ->searchable()
                        ->nullable(),
                    Toggle::make('timpa')
                        ->label('Timpa pemetaan yang sudah ada')
                        ->default(false),
                ])
                ->requiresConfirmation()
                ->modalHeading('Terapkan Template Kesetaraan')
                ->modalDescription('Kelas aktif diurutkan menurut tingkat lalu dipetakan berurutan ke jenjang kesetaraan PKPPS/SPM.')
                ->action(function (array $data): void {
                    $jumlah = app(KesetaraanTemplateService::class)->terapkan(
                        filled($data['lembaga_id'] ?? null) ? (int) $data['lembaga_id'] : null,
                        (bool) ($data['timpa'] ?? false),
                    );

                    Notification::make()
                        ->title($jumlah > 0
                            ? "Template kesetaraan diterapkan pada {$jumlah} kelas."
                            : 'Tidak ada kelas yang dipetakan (sudah terisi atau tidak ada kelas aktif).')
                        ->{$jumlah > 0 ? 'success' : 'warning'}()
                        ->send();
                }),
        ];
    }
}
