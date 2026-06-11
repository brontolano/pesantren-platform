<?php

namespace App\Filament\Resources\Alumni\Pages;

use App\Filament\Resources\Alumni\AlumniResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Services\KelulusanService;
use Modules\Kesantrian\Models\Kelas;
use Throwable;

class ListAlumni extends ListRecords
{
    protected static string $resource = AlumniResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // WP-08 — eksekusi keputusan 'lulus' FINAL hasil sidang WP-07.
            Action::make('proses_kelulusan')
                ->label('Proses Kelulusan')
                ->icon('heroicon-o-academic-cap')
                ->visible(fn (): bool => auth()->user()?->can('manage_santri') ?? false)
                ->schema([
                    Select::make('periode_akademik_id')
                        ->label('Periode Akademik')
                        ->options(fn () => PeriodeAkademik::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id'))
                        ->required(),
                    Select::make('kelas_id')
                        ->label('Kelas Asal (opsional — kosong = semua)')
                        ->options(fn () => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id'))
                        ->nullable(),
                ])
                ->requiresConfirmation()
                ->modalDescription('Hanya keputusan LULUS yang sudah FINAL di sidang kenaikan yang diproses. Aman dijalankan ulang (idempoten).')
                ->action(function (array $data): void {
                    try {
                        $periode = PeriodeAkademik::query()->findOrFail($data['periode_akademik_id']);
                        $kelas = filled($data['kelas_id'] ?? null) ? Kelas::query()->find($data['kelas_id']) : null;

                        $jumlah = app(KelulusanService::class)->prosesLulus($periode, $kelas, auth()->user());

                        Notification::make()->success()
                            ->title($jumlah.' santri diproses lulus menjadi alumni.')->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('Gagal memproses kelulusan')->body($e->getMessage())->send();
                    }
                }),
        ];
    }
}
