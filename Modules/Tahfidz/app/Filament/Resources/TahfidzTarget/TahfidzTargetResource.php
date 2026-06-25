<?php

namespace Modules\Tahfidz\Filament\Resources\TahfidzTarget;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Filament\Resources\TahfidzTarget\Pages\CreateTahfidzTarget;
use Modules\Tahfidz\Filament\Resources\TahfidzTarget\Pages\EditTahfidzTarget;
use Modules\Tahfidz\Filament\Resources\TahfidzTarget\Pages\ListTahfidzTarget;
use Modules\Tahfidz\Models\TahfidzTarget;
use Modules\Tahfidz\Services\TahfidzKpiService;

class TahfidzTargetResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = TahfidzTarget::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Target Tahfidz (KPI)';

    protected static ?string $modelLabel = 'Target Tahfidz';

    protected static ?string $pluralModelLabel = 'Target Tahfidz (KPI)';

    protected static string|\UnitEnum|null $navigationGroup = 'Tahfidz';

    protected static ?int $navigationSort = 6;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('tahfidz') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Target Hafalan (KPI)')->schema([
                Select::make('santri_id')->label('Santri')->required()->searchable()
                    ->options(fn (): array => Santri::query()->orderBy('nama')->limit(200)
                        ->get()->mapWithKeys(fn (Santri $s): array => [$s->id => $s->nama])->all()),
                TextInput::make('periode')->label('Periode')->maxLength(20)->placeholder('2026 / 2026-S1'),
                TextInput::make('target_surah')->label('Target (jumlah surah/hafalan)')->numeric()->required()->minValue(0),
                TextInput::make('keterangan')->label('Keterangan')->maxLength(255),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->searchable(),
                TextColumn::make('periode')->label('Periode')->placeholder('—'),
                TextColumn::make('target_surah')->label('Target')->numeric()->alignEnd(),
                TextColumn::make('realisasi')->label('Realisasi (periode)')
                    ->state(fn (TahfidzTarget $r): int => app(TahfidzKpiService::class)->realisasi($r->santri_id, (string) $r->periode))
                    ->alignEnd(),
                TextColumn::make('capaian')->label('Capaian %')
                    ->state(fn (TahfidzTarget $r): float => app(TahfidzKpiService::class)->capaian($r->santri_id, (string) $r->periode)['capaian'])
                    ->formatStateUsing(fn ($s): string => number_format((float) $s, 1).'%')
                    ->color(fn ($s): string => (float) $s >= 100 ? 'success' : ((float) $s >= 60 ? 'warning' : 'danger'))->alignEnd(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTahfidzTarget::route('/'),
            'create' => CreateTahfidzTarget::route('/create'),
            'edit' => EditTahfidzTarget::route('/{record}/edit'),
        ];
    }
}
