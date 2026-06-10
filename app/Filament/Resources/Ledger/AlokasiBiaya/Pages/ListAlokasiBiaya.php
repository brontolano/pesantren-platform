<?php

namespace App\Filament\Resources\Ledger\AlokasiBiaya\Pages;

use App\Filament\Resources\Ledger\AlokasiBiaya\AlokasiBiayaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\DivisiUnit;
use Modules\Ledger\Services\AlokasiBiayaService;

class ListAlokasiBiaya extends ListRecords
{
    protected static string $resource = AlokasiBiayaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buat_alokasi')
                ->label('Buat Alokasi')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->visible(fn (): bool => auth()->user()?->can('manage_ledger') ?? false)
                ->schema([
                    Select::make('akun_id')->label('Akun Beban')->required()->native(false)->searchable()
                        ->options(fn (): array => Akun::query()->where('tipe', 'BEBAN')->where('is_postable', true)->orderBy('kode')
                            ->get()->mapWithKeys(fn (Akun $a): array => [$a->id => $a->kode.' — '.$a->nama])->all()),
                    TextInput::make('nominal')->label('Total Nominal (Rp)')->numeric()->required()->minValue(1)->prefix('Rp'),
                    TextInput::make('periode')->label('Periode')->maxLength(20)->placeholder('2026-06'),
                    Repeater::make('driver')->label('Driver per Divisi')->required()
                        ->schema([
                            Select::make('divisi_id')->label('Divisi')->required()->native(false)
                                ->options(fn (): array => DivisiUnit::query()->orderBy('nama_divisi')
                                    ->get()->mapWithKeys(fn (DivisiUnit $d): array => [$d->id => $d->nama_divisi])->all()),
                            TextInput::make('bobot')->label('Bobot')->numeric()->required()->minValue(0)->default(1),
                        ])->columns(2)->minItems(1)->defaultItems(1)->addActionLabel('Tambah Divisi'),
                ])
                ->action(function (array $data): void {
                    try {
                        $driver = collect($data['driver'])->map(fn ($d): array => [
                            'divisi_id' => (int) $d['divisi_id'], 'bobot' => (float) $d['bobot'],
                        ])->all();
                        app(AlokasiBiayaService::class)->alokasikan((int) $data['akun_id'], (float) $data['nominal'], $driver, [
                            'periode' => $data['periode'] ?? null,
                            'dibuat_oleh' => auth()->id(),
                        ]);
                        Notification::make()->title('Alokasi biaya diposting (jurnal seimbang)')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Alokasi gagal')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
