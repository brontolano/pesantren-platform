<?php

namespace App\Filament\Resources\Mutu;

use App\Filament\Resources\Mutu\MutuSkm\Pages\CreateMutuSkm;
use App\Filament\Resources\Mutu\MutuSkm\Pages\ListMutuSkm;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Mutu\Models\MutuSkm;

class MutuSkmResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = MutuSkm::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-face-smile';

    protected static ?string $navigationLabel = 'SKM (Kepuasan Wali)';

    protected static ?string $modelLabel = 'SKM';

    protected static ?string $pluralModelLabel = 'SKM (Kepuasan Wali)';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan & Mutu';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('mutu') && (auth()->user()?->can('manage_mutu') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Survei Kepuasan Masyarakat (SKM)')->schema([
                TextInput::make('periode')->label('Periode')->maxLength(20)->placeholder('2026 / 2026-S1'),
                Select::make('aspek')->label('Aspek')->native(false)
                    ->options(['layanan' => 'Layanan', 'akademik' => 'Akademik', 'sarana' => 'Sarana', 'umum' => 'Umum'])
                    ->default('umum'),
                TextInput::make('responden')->label('Responden')->maxLength(255)->placeholder('opsional/anonim'),
                TextInput::make('skor')->label('Skor (1-5)')->numeric()->required()->minValue(1)->maxValue(5),
                Textarea::make('komentar')->label('Komentar')->rows(2)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('periode')->label('Periode')->placeholder('—')->sortable(),
                TextColumn::make('aspek')->label('Aspek')->badge(),
                TextColumn::make('responden')->label('Responden')->placeholder('Anonim')->toggleable(),
                TextColumn::make('skor')->label('Skor')->badge()
                    ->color(fn ($s): string => (int) $s >= 4 ? 'success' : ((int) $s >= 3 ? 'warning' : 'danger'))->alignCenter(),
                TextColumn::make('komentar')->label('Komentar')->limit(40)->placeholder('—')->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('aspek')->options(['layanan' => 'Layanan', 'akademik' => 'Akademik', 'sarana' => 'Sarana', 'umum' => 'Umum']),
            ])
            ->actions([DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMutuSkm::route('/'),
            'create' => CreateMutuSkm::route('/create'),
        ];
    }
}
