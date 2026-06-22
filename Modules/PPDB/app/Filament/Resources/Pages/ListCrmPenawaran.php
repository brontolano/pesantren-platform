<?php

namespace Modules\PPDB\Filament\Resources\Pages;

use Modules\PPDB\Filament\Resources\CrmPenawaranResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Modules\PPDB\Services\CrmService;

class ListCrmPenawaran extends ListRecords
{
    protected static string $resource = CrmPenawaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buat_penawaran')
                ->label('Buat Penawaran')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->schema([
                    TextInput::make('nama_prospek')->label('Nama Prospek')->maxLength(255),
                    TextInput::make('tahun_ajaran')->label('Tahun Ajaran')->required()->placeholder('2026/2027'),
                    TextInput::make('jenis')->label('Jenis biaya (pisahkan koma)')->required()
                        ->placeholder('pendaftaran, spp, seragam')
                        ->helperText('Nominal diambil dari Skema Biaya (G2-16) yang aktif.'),
                ])
                ->action(function (array $data): void {
                    $jenis = collect(explode(',', (string) $data['jenis']))->map(fn ($s): string => trim($s))->filter()->values()->all();
                    $p = app(CrmService::class)->buatPenawaran([
                        'nama_prospek' => $data['nama_prospek'] ?? null,
                        'tahun_ajaran' => $data['tahun_ajaran'],
                        'jenis' => $jenis,
                        'dibuat_oleh' => auth()->id(),
                    ]);
                    Notification::make()
                        ->title($p->total > 0 ? 'Penawaran dibuat — total Rp '.number_format((float) $p->total, 0, ',', '.') : 'Penawaran dibuat (skema tak ditemukan, total 0)')
                        ->success()->send();
                }),
            Action::make('campaign_daftar_ulang')
                ->label('Campaign Daftar Ulang')
                ->icon('heroicon-o-megaphone')
                ->color('info')
                ->schema([
                    TextInput::make('tahun_ajaran')->label('Tahun Ajaran')->required()->placeholder('2026/2027'),
                ])
                ->action(function (array $data): void {
                    $n = app(CrmService::class)->kirimCampaignDaftarUlang((string) $data['tahun_ajaran'], ['dibuat_oleh' => auth()->id()]);
                    Notification::make()
                        ->title($n > 0 ? "$n reminder daftar ulang terkirim" : 'Tidak ada target baru (idempoten / semua sudah dikirim)')
                        ->success()->send();
                }),
        ];
    }
}
