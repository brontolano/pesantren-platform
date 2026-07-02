<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Modules\Keuangan\Models\Pembayaran;

/**
 * Tabel "Transaksi Terbaru" (mockup): pembayaran terakhir (kas masuk).
 * Kolom: Tanggal, Keterangan, Tipe (badge), Jumlah. Read-only di dashboard.
 * Saat modul kas keluar ada, sumber bisa digabung (union) — kini hanya masuk.
 */
class TransaksiTerbaru extends TableWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->can('view_laporan_keuangan') ?? false;
    }

    protected static ?int $sort = -4;

    protected int|string|array $columnSpan = 4;

    // WAJIB static — TableWidget::$heading dideklarasikan static (beda dgn
    // ChartWidget/StatsOverviewWidget yang non-static). Non-static → fatal saat load.
    protected static ?string $heading = 'Transaksi Terbaru';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Pembayaran::query()
                    ->with('santri')
                    ->latest('tanggal_pembayaran')
            )
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10])
            ->columns([
                TextColumn::make('tanggal_pembayaran')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('santri.nama')
                    ->label('Keterangan')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'Pembayaran — '.$state : 'Pembayaran')
                    ->description(fn (Pembayaran $record): ?string => $record->metode ? 'via '.$record->metode : null)
                    ->wrap(),
                TextColumn::make('tipe')
                    ->label('Tipe')
                    ->badge()
                    ->state(fn (): string => 'Masuk')
                    ->color('success'),
                TextColumn::make('nominal')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->weight('bold')
                    ->alignEnd(),
            ])
            ->emptyStateHeading('Belum ada transaksi')
            ->emptyStateDescription('Pembayaran yang masuk akan tampil di sini.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
