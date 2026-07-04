<?php

namespace Modules\Akademik\Filament\Resources\Rubrik;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Forms\SantriSelect;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Akademik\Filament\Resources\Rubrik\Pages\CreateRubrikKriteria;
use Modules\Akademik\Filament\Resources\Rubrik\Pages\EditRubrikKriteria;
use Modules\Akademik\Filament\Resources\Rubrik\Pages\ListRubrikKriteria;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\RubrikKriteria;
use Modules\Akademik\Services\RubrikService;

class RubrikKriteriaResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = RubrikKriteria::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Rubrik Kompetensi';

    protected static ?string $modelLabel = 'Kriteria Rubrik';

    protected static ?string $pluralModelLabel = 'Rubrik Kompetensi';

    protected static string|\UnitEnum|null $navigationGroup = 'Nilai & Rapor';

    protected static ?int $navigationSort = 9;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kriteria Rubrik (berbobot)')->schema([
                TextInput::make('nama')->label('Nama Kriteria')->required()->maxLength(255),
                Select::make('pelajaran_id')->label('Pelajaran (opsional)')->native(false)->searchable()
                    ->options(fn (): array => Pelajaran::query()->orderBy('nama')->limit(200)
                        ->get()->mapWithKeys(fn (Pelajaran $p): array => [$p->id => $p->nama])->all()),
                TextInput::make('bobot')->label('Bobot')->numeric()->default(1)->minValue(0)->step(0.5)
                    ->helperText('Bobot relatif. Agregat = Σ(skor×bobot)/Σbobot.'),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Kriteria')->searchable(),
                TextColumn::make('pelajaran.nama')->label('Pelajaran')->placeholder('Umum'),
                TextColumn::make('bobot')->label('Bobot')->numeric()->alignEnd(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('nama')
            ->actions([
                Action::make('catat_skor')
                    ->label('Catat Skor')
                    ->icon('heroicon-o-pencil-square')
                    ->color('success')
                    ->visible(fn (): bool => auth()->user()?->can('manage_akademik') ?? false)
                    ->schema([
                        SantriSelect::make('santri_id')->required(),
                        TextInput::make('skor')->label('Skor (0-100)')->numeric()->required()->minValue(0)->maxValue(100),
                    ])
                    ->action(function (array $data, RubrikKriteria $record): void {
                        app(RubrikService::class)->catatSkor($record, (int) $data['santri_id'], (float) $data['skor']);
                        Notification::make()->title('Skor rubrik dicatat')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRubrikKriteria::route('/'),
            'create' => CreateRubrikKriteria::route('/create'),
            'edit' => EditRubrikKriteria::route('/{record}/edit'),
        ];
    }
}
