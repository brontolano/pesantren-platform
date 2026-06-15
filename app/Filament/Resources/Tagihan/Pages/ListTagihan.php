<?php

namespace App\Filament\Resources\Tagihan\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Tagihan\TagihanResource;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Modules\Keuangan\Services\KeuanganImportService;

class ListTagihan extends ListRecords
{
    use HasImportAction;

    protected static string $resource = TagihanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->buildImportAction([
                'entitas' => 'tagihan',
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

