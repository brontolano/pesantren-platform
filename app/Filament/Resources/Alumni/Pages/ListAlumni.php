<?php

namespace App\Filament\Resources\Alumni\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Alumni\AlumniResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Services\KelulusanService;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Services\AlumniImportService;
use Throwable;

class ListAlumni extends ListRecords
{
    use HasImportAction;

    protected static string $resource = AlumniResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->importActions([
                'entitas' => 'alumni',
                'template' => ['nis', 'tahun_lulus', 'status_lanjutan', 'sekolah_tujuan', 'catatan'],
                'template_contoh' => ['2019001', '2024', 'kuliah', 'UIN Bandung', ''],
                'export' => fn (\App\Models\Tenant $t) => \Modules\Kesantrian\Models\Alumni::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with('santri')->get()
                    ->map(fn ($a): array => ['nis' => $a->santri?->nis, 'tahun_lulus' => $a->tahun_lulus, 'status_lanjutan' => $a->status_lanjutan, 'sekolah_tujuan' => $a->sekolah_tujuan, 'catatan' => $a->catatan]),
                'label' => 'Import Alumni',
                'service' => AlumniImportService::class,
                'directory' => 'imports/kesantrian/alumni/uploads',
                'ability' => 'manage_santri',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nis (wajib, santri harus sudah ada), tahun_lulus (wajib), status_lanjutan, sekolah_tujuan, catatan. 1 alumni per santri (yang sudah ada dilewati). Dry-run dulu.',
            ]),
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
