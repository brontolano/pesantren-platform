<?php

namespace Modules\Akademik\Filament\Resources\Rapor;

use App\Filament\Concerns\ModuleGate;
use App\Support\PrintContent;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Akademik\Filament\Resources\Rapor\Pages\ListRapor;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\Rapor;
use Modules\Akademik\Services\RaporService;
use Modules\Kesantrian\Models\Kelas;
use Torgodly\Html2Media\Actions\Html2MediaAction;

class RaporResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Rapor::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Rapor';

    protected static string|\UnitEnum|null $navigationGroup = 'Nilai & Rapor';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->searchable(),
                TextColumn::make('santri.kelas.nama_kelas')->label('Kelas')->sortable(),
                TextColumn::make('periodeAkademik.nama')->label('Periode')->sortable(),
                TextColumn::make('total_sks')->label('Total SKS'),
                TextColumn::make('ipk')->label('IPK')->badge()->sortable(),
                TextColumn::make('rank_kelas')->label('Peringkat')->sortable(),
                TextColumn::make('generated_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->headerActions([
                Action::make('generateRaporKelas')
                    ->label('Generate Rapor Kelas')
                    ->icon('heroicon-o-sparkles')
                    ->visible(fn (): bool => auth()->user()?->can('manage_akademik') ?? false)
                    ->form([
                        Select::make('periode_akademik_id')
                            ->label('Periode Akademik')
                            ->options(fn () => PeriodeAkademik::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id'))
                            ->required()
                            ->searchable(),
                        Select::make('kelas_id')
                            ->label('Kelas')
                            ->options(fn () => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id'))
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (array $data): void {
                        $periode = PeriodeAkademik::query()->findOrFail($data['periode_akademik_id']);
                        $kelas = Kelas::query()->findOrFail($data['kelas_id']);
                        app(RaporService::class)->generateForKelas($periode, $kelas);
                    }),
            ])
            ->actions([
                Action::make('print')
                    ->label('Cetak / Simpan PDF')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Rapor $record): string => route('tenant.akademik.export.rapor.print', $record))
                    ->openUrlInNewTab(false)
                    ->visible(fn () => tenancy()->initialized),
                // Adopsi html2media: unduh PDF rapi / cetak dari modal (tanpa header
                // URL & margin liar). Blade + data identik dengan route cetak di atas
                // (jalur window.print lama dipertahankan sbg fallback).
                Html2MediaAction::make('pdf_rapor')
                    ->label('Unduh PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->format('a4')
                    ->orientation('portrait')
                    ->preview()
                    ->savePdf()
                    ->filename(fn (Rapor $record): string => 'rapor-'.($record->santri?->nis ?: $record->getKey()))
                    ->visible(fn () => tenancy()->initialized)
                    ->content(fn (Rapor $record) => PrintContent::rapor($record)),
            ])
            ->defaultSort('generated_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['santri.kelas', 'periodeAkademik']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRapor::route('/'),
        ];
    }
}
