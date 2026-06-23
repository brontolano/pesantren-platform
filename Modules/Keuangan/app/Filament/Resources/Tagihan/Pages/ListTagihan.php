<?php

namespace Modules\Keuangan\Filament\Resources\Tagihan\Pages;

use App\Filament\Concerns\HasImportAction;
use Modules\Keuangan\Filament\Resources\Tagihan\TagihanResource;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Modules\Kesantrian\Models\Kelas;
use Modules\Keuangan\Services\KeuanganImportService;
use Modules\Keuangan\Services\TagihanService;

class ListTagihan extends ListRecords
{
    use HasImportAction;

    protected static string $resource = TagihanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('buatPerKelas')
                ->label('Buat Tagihan per Kelas')
                ->icon('heroicon-o-user-group')
                ->color('primary')
                ->modalHeading('Buat Tagihan untuk Satu Kelas')
                ->modalDescription('Membuat satu tagihan dengan nominal sama untuk semua santri aktif di kelas terpilih.')
                ->modalSubmitActionLabel('Buat Tagihan')
                ->visible(fn (): bool => tenancy()->initialized && (auth()->user()?->can('manage_keuangan') ?? false))
                ->schema([
                    Select::make('kelas_id')
                        ->label('Kelas')
                        ->options(fn () => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id'))
                        ->searchable()
                        ->required(),
                    Select::make('jenis_tagihan')
                        ->label('Jenis Tagihan')
                        ->options([
                            'spp' => 'SPP',
                            'infaq' => 'Infaq',
                            'seragam' => 'Seragam',
                            'kitab' => 'Kitab',
                            'daftar_ulang' => 'Daftar Ulang',
                            'lainnya' => 'Lainnya',
                        ])
                        ->required(),
                    TextInput::make('keterangan')
                        ->label('Keterangan')
                        ->maxLength(255),
                    TextInput::make('nominal')
                        ->label('Nominal')
                        ->numeric()
                        ->prefix('Rp')
                        ->required()
                        ->minValue(0),
                    TextInput::make('diskon')
                        ->label('Diskon')
                        ->numeric()
                        ->prefix('Rp')
                        ->default(0)
                        ->minValue(0),
                    DatePicker::make('jatuh_tempo')
                        ->label('Jatuh Tempo')
                        ->required(),
                    TextInput::make('periode_akademik')
                        ->label('Periode Akademik')
                        ->placeholder('2026/2027')
                        ->maxLength(20),
                    TextInput::make('bulan_masehi')
                        ->label('Bulan Masehi')
                        ->placeholder('2026-07')
                        ->maxLength(7),
                    TextInput::make('bulan_hijriah')
                        ->label('Bulan Hijriah')
                        ->placeholder('Dzulhijjah 1447')
                        ->helperText('Wajib diisi bila mode periode keuangan hijriah/keduanya.')
                        ->maxLength(50),
                ])
                ->action(function (array $data): void {
                    try {
                        $payload = [
                            'jenis_tagihan' => $data['jenis_tagihan'],
                            'keterangan' => $data['keterangan'] ?? null,
                            'nominal' => $data['nominal'],
                            'diskon' => $data['diskon'] ?? 0,
                            'jatuh_tempo' => $data['jatuh_tempo'],
                            'periode_akademik' => $data['periode_akademik'] ?? null,
                            'bulan_masehi' => ($data['bulan_masehi'] ?? '') !== '' ? $data['bulan_masehi'] : null,
                            'bulan_hijriah' => ($data['bulan_hijriah'] ?? '') !== '' ? $data['bulan_hijriah'] : null,
                            'created_by' => auth()->id(),
                        ];

                        $tagihan = app(TagihanService::class)
                            ->buatTagihanPerKelas((int) $data['kelas_id'], $payload);

                        $jumlah = $tagihan->count();

                        if ($jumlah === 0) {
                            Notification::make()
                                ->title('Tidak ada tagihan dibuat')
                                ->body('Kelas terpilih tidak memiliki santri berstatus aktif.')
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Tagihan per kelas berhasil dibuat')
                            ->body("{$jumlah} tagihan dibuat untuk santri aktif di kelas terpilih.")
                            ->success()
                            ->send();
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Tagihan tidak dapat dibuat')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            ...$this->importActions([
                'entitas' => 'tagihan',
                'template' => ['nis', 'jenis_tagihan', 'nominal', 'jatuh_tempo', 'diskon', 'bulan', 'status_bayar', 'tanggal_pembayaran', 'metode', 'nomor_bukti'],
                'template_contoh' => ['2024001', 'SPP', '300000', '2026-07-10', '0', '2026-07', 'belum', '', '', ''],
                'label' => 'Import Tagihan/SPP',
                'service' => KeuanganImportService::class,
                'directory' => 'imports/keuangan/uploads',
                'ability' => 'manage_keuangan',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nis, jenis_tagihan, nominal, jatuh_tempo, diskon, bulan (YYYY-MM), status_bayar (lunas/belum), tanggal_pembayaran, metode, nomor_bukti. Baris "lunas" WAJIB memilih verifikator di bawah. Idempoten — aman diulang (dry-run dulu).',
                'schema' => [
                    Select::make('verifikator_id')
                        ->label('Verifikator pelunasan (untuk baris lunas)')
                        ->helperText('User yang tercatat memverifikasi pelunasan historis. Wajib bila ada baris status "lunas".')
                        ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->native(false),
                ],
                'invoke' => function (KeuanganImportService $service, Tenant $tenant, string $path, bool $dryRun, array $data): array {
                    $verifikatorId = isset($data['verifikator_id']) && $data['verifikator_id'] !== ''
                        ? (int) $data['verifikator_id']
                        : null;
                    $summary = $service->import($tenant, $path, $verifikatorId, $dryRun);
                    // Normalisasi ke kontrak trait (service Keuangan tak punya 'imported').
                    $summary['imported'] = $summary['tagihan_dibuat'] ?? 0;

                    return $summary;
                },
                'extraSummary' => fn (array $s): string => sprintf(
                    'Pelunasan diverifikasi: %d  •  Total tagihan: Rp %s',
                    $s['pembayaran_diverifikasi'] ?? 0,
                    number_format((float) ($s['total_tagihan'] ?? 0), 0, ',', '.'),
                ),
            ]),
            Action::make('export_csv')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn () => route('tenant.keuangan.export.tagihan.csv', [], false))
                ->openUrlInNewTab()
                ->visible(fn () => tenancy()->initialized),
            Action::make('export_print')
                ->label('Cetak / PDF')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('tenant.keuangan.export.tagihan.print', [], false))
                ->openUrlInNewTab()
                ->visible(fn () => tenancy()->initialized),
        ];
    }
}

