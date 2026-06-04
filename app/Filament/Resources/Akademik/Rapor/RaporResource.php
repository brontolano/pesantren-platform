<?php

namespace App\Filament\Resources\Akademik\Rapor;

use App\Filament\Resources\Akademik\Rapor\Pages\ListRapor;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\Rapor;
use Modules\Akademik\Services\RaporService;
use Modules\Kesantrian\Models\Kelas;

class RaporResource extends Resource
{
    protected static ?string $model = Rapor::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Rapor';

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false;
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
                        \Filament\Forms\Components\Select::make('periode_akademik_id')
                            ->label('Periode Akademik')
                            ->options(fn () => PeriodeAkademik::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id'))
                            ->required()
                            ->searchable(),
                        \Filament\Forms\Components\Select::make('kelas_id')
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
                    ->openUrlInNewTab(false),
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

