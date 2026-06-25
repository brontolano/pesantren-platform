<?php

namespace Modules\Mutu\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Mutu\Filament\Resources\MutuSasaran\Pages\CreateMutuSasaran;
use Modules\Mutu\Filament\Resources\MutuSasaran\Pages\EditMutuSasaran;
use Modules\Mutu\Filament\Resources\MutuSasaran\Pages\ListMutuSasaran;
use Modules\Mutu\Models\MutuSasaran;
use Modules\Mutu\Services\MutuKpiService;

class MutuSasaranResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = MutuSasaran::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Sasaran Mutu (KPI)';

    protected static ?string $modelLabel = 'Sasaran Mutu';

    protected static ?string $pluralModelLabel = 'Sasaran Mutu (KPI)';

    protected static string|\UnitEnum|null $navigationGroup = 'Mutu';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('mutu') && (auth()->user()?->can('manage_mutu') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sasaran / KPI Mutu (KMA 939)')->schema([
                TextInput::make('nama')->label('Nama Sasaran')->required()->maxLength(255),
                TextInput::make('kode')->label('Kode')->maxLength(50),
                Select::make('area')->label('Area')->native(false)->options(MutuSasaran::AREA)->default('umum'),
                TextInput::make('satuan')->label('Satuan')->maxLength(30)->placeholder('%, nilai, orang'),
                TextInput::make('target')->label('Target')->numeric()->default(0)->minValue(0),
                TextInput::make('realisasi')->label('Realisasi')->numeric()->default(0)->minValue(0),
                TextInput::make('periode')->label('Periode')->maxLength(20)->placeholder('2026 / 2026-S1'),
                TextInput::make('regulasi_acuan')->label('Regulasi Acuan')->maxLength(255)->placeholder('KMA 939/2018'),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Sasaran')->searchable()->wrap(),
                TextColumn::make('area')->label('Area')->badge()
                    ->formatStateUsing(fn (?string $s): string => MutuSasaran::AREA[$s] ?? $s),
                TextColumn::make('target')->label('Target')->numeric()->alignEnd(),
                TextColumn::make('realisasi')->label('Realisasi')->numeric()->alignEnd(),
                TextColumn::make('capaian')->label('Capaian %')
                    ->state(fn (MutuSasaran $r): float => $r->capaian())
                    ->formatStateUsing(fn ($s): string => number_format((float) $s, 1).'%')
                    ->color(fn ($s): string => (float) $s >= 100 ? 'success' : ((float) $s >= 75 ? 'warning' : 'danger'))->alignEnd(),
                TextColumn::make('periode')->label('Periode')->placeholder('—'),
            ])
            ->defaultSort('area')
            ->filters([
                SelectFilter::make('area')->options(MutuSasaran::AREA),
            ])
            ->actions([
                Action::make('realisasi')
                    ->label('Catat Realisasi')
                    ->icon('heroicon-o-pencil-square')
                    ->color('success')
                    ->schema([
                        TextInput::make('realisasi')->label('Nilai realisasi')->numeric()->required()->minValue(0),
                    ])
                    ->fillForm(fn (MutuSasaran $record): array => ['realisasi' => $record->realisasi])
                    ->action(function (array $data, MutuSasaran $record): void {
                        try {
                            app(MutuKpiService::class)->catatRealisasi($record, (float) $data['realisasi']);
                            Notification::make()->title('Realisasi diperbarui')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal mencatat realisasi')->body($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMutuSasaran::route('/'),
            'create' => CreateMutuSasaran::route('/create'),
            'edit' => EditMutuSasaran::route('/{record}/edit'),
        ];
    }
}
