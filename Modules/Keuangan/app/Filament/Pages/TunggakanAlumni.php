<?php

namespace Modules\Keuangan\Filament\Pages;

use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Kesantrian\Models\Santri;

/**
 * Halaman bendahara: daftar ALUMNI (status 'lulus') yang masih menunggak,
 * beserta jumlah tagihan menunggak & total piutangnya, agar mudah ditindaklanjuti.
 *
 * Auto-discovered via TenantPanelProvider->discoverPages(); tidak perlu daftar manual.
 * Status tagihan yang dihitung "menunggak" = belum_bayar + sebagian.
 */
class TunggakanAlumni extends Page implements HasTable
{
    use InteractsWithTable;
    use \App\Filament\Concerns\ModuleGate;

    /** Status tagihan yang dianggap menunggak. */
    private const STATUS_MENUNGGAK = ['belum_bayar', 'sebagian'];

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Tunggakan Alumni';

    protected static string|\UnitEnum|null $navigationGroup = 'Tagihan Santri';

    protected static ?int $navigationSort = 8;

    protected static ?string $title = 'Tunggakan Alumni';

    protected string $view = 'filament.pages.tunggakan-alumni';

    public static function canAccess(): bool
    {
        return static::moduleVisible('keuangan')
            && (auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::canAccess();
    }

    public function getSubheading(): ?string
    {
        $base = static::baseQuery();

        $jumlahAlumni = (clone $base)->count();
        $totalPiutang = (float) (clone $base)
            ->join('tagihan', 'tagihan.santri_id', '=', 'santri.id')
            ->whereIn('tagihan.status', self::STATUS_MENUNGGAK)
            ->whereNull('tagihan.deleted_at')
            ->sum('tagihan.nominal_akhir');

        return 'Total piutang alumni: Rp '.number_format($totalPiutang, 0, ',', '.')
            .' — dari '.$jumlahAlumni.' alumni penunggak.';
    }

    /**
     * Alumni (santri lulus) yang punya minimal satu tagihan menunggak.
     */
    protected static function baseQuery(): Builder
    {
        return Santri::query()
            ->where('santri.status', 'lulus')
            ->whereHas('tagihan', fn (Builder $q) => $q->whereIn('status', self::STATUS_MENUNGGAK));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => static::baseQuery()
                    ->with('alumni')
                    ->withCount([
                        'tagihan as jumlah_tunggakan' => fn (Builder $q) => $q->whereIn('status', self::STATUS_MENUNGGAK),
                    ])
                    ->withSum(
                        ['tagihan as total_tunggakan' => fn (Builder $q) => $q->whereIn('status', self::STATUS_MENUNGGAK)],
                        'nominal_akhir'
                    )
            )
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama Alumni')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nis')
                    ->label('NIS')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tahun_lulus')
                    ->label('Tahun Lulus')
                    ->getStateUsing(fn (Santri $record): ?string => $record->alumni?->tahun_lulus)
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('status_lanjutan')
                    ->label('Status Lanjutan')
                    ->getStateUsing(fn (Santri $record): ?string => $record->alumni?->status_lanjutan)
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('no_hp_wali')
                    ->label('No. HP Wali')
                    ->icon('heroicon-o-phone')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('jumlah_tunggakan')
                    ->label('Jml Tagihan')
                    ->badge()
                    ->color('warning')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('total_tunggakan')
                    ->label('Total Tunggakan')
                    ->money('IDR')
                    ->weight('bold')
                    ->color('danger')
                    ->sortable(),
            ])
            ->defaultSort('total_tunggakan', 'desc')
            ->actions([
                Action::make('whatsapp')
                    ->label('Hubungi (WA)')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (Santri $record): ?string => static::waUrl($record->no_hp_wali))
                    ->openUrlInNewTab()
                    ->visible(fn (Santri $record): bool => filled($record->no_hp_wali)),
            ])
            ->emptyStateHeading('Tidak ada tunggakan alumni')
            ->emptyStateDescription('Alhamdulillah, belum ada alumni yang masih menunggak.')
            ->emptyStateIcon('heroicon-o-check-badge');
    }

    /**
     * Bangun URL wa.me dari nomor HP (normalisasi 0 → 62).
     */
    protected static function waUrl(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (blank($digits)) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (! str_starts_with($digits, '62')) {
            $digits = '62'.$digits;
        }

        return 'https://wa.me/'.$digits;
    }
}
