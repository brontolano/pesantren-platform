<?php

namespace App\Filament\Resources\LaporanKeuangan\Pages;

use App\Filament\Resources\LaporanKeuangan\LaporanKeuanganResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListLaporanKeuangan extends ListRecords
{
    protected static string $resource = LaporanKeuanganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportTagihanCsv')
                ->label('Export Excel Tagihan')
                ->icon('heroicon-o-table-cells')
                ->form($this->tagihanFilterForm())
                ->action(fn (array $data) => redirect()->route('tenant.keuangan.export.tagihan.csv', $this->normalizeFilters($data)))
                ->visible(fn () => tenancy()->initialized),
            Action::make('printTagihanPdf')
                ->label('Cetak PDF Tagihan')
                ->icon('heroicon-o-printer')
                ->form($this->tagihanFilterForm())
                ->action(fn (array $data) => redirect()->route('tenant.keuangan.export.tagihan.print', $this->normalizeFilters($data)))
                ->openUrlInNewTab(false)
                ->visible(fn () => tenancy()->initialized),
            Action::make('exportPembayaranCsv')
                ->label('Export Excel Pembayaran')
                ->icon('heroicon-o-banknotes')
                ->form($this->pembayaranFilterForm())
                ->action(fn (array $data) => redirect()->route('tenant.keuangan.export.pembayaran.csv', $this->normalizeFilters($data)))
                ->visible(fn () => tenancy()->initialized),
            Action::make('printPembayaranPdf')
                ->label('Cetak PDF Pembayaran')
                ->icon('heroicon-o-document-arrow-down')
                ->form($this->pembayaranFilterForm())
                ->action(fn (array $data) => redirect()->route('tenant.keuangan.export.pembayaran.print', $this->normalizeFilters($data)))
                ->openUrlInNewTab(false)
                ->visible(fn () => tenancy()->initialized),
        ];
    }

    private function tagihanFilterForm(): array
    {
        return [
            Select::make('status')
                ->label('Status')
                ->options([
                    '' => 'Semua status',
                    'belum_bayar' => 'Belum Bayar',
                    'sebagian' => 'Sebagian',
                    'lunas' => 'Lunas',
                    'batal' => 'Batal',
                ])
                ->default(''),
            Select::make('jenis_tagihan')
                ->label('Jenis Tagihan')
                ->options([
                    '' => 'Semua jenis',
                    'spp' => 'SPP',
                    'daftar_ulang' => 'Daftar Ulang',
                    'uang_makan' => 'Uang Makan',
                    'tabungan' => 'Tabungan',
                    'lainnya' => 'Lainnya',
                ])
                ->default(''),
            Select::make('periode_akademik')
                ->label('Periode Akademik')
                ->options(fn (): array => \Modules\Keuangan\Models\Tagihan::query()
                    ->whereNotNull('periode_akademik')
                    ->distinct()
                    ->orderByDesc('periode_akademik')
                    ->pluck('periode_akademik', 'periode_akademik')
                    ->toArray())
                ->searchable(),
            DatePicker::make('dari')
                ->label('Dari Tanggal'),
            DatePicker::make('sampai')
                ->label('Sampai Tanggal'),
        ];
    }

    private function pembayaranFilterForm(): array
    {
        return [
            Select::make('metode')
                ->label('Metode')
                ->options([
                    '' => 'Semua metode',
                    'tunai' => 'Tunai',
                    'transfer' => 'Transfer',
                    'qris' => 'QRIS',
                    'lainnya' => 'Lainnya',
                ])
                ->default(''),
            Select::make('verified')
                ->label('Status Verifikasi')
                ->options([
                    '' => 'Semua status',
                    '1' => 'Sudah diverifikasi',
                    '0' => 'Belum diverifikasi',
                ])
                ->default(''),
            DatePicker::make('dari')
                ->label('Dari Tanggal'),
            DatePicker::make('sampai')
                ->label('Sampai Tanggal'),
        ];
    }

    private function normalizeFilters(array $data): array
    {
        return collect($data)
            ->filter(fn ($value): bool => ! ($value === null || $value === ''))
            ->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value)
            ->all();
    }
}

