<?php

namespace App\Filament\Resources\Akademik\Program;

use App\Filament\Resources\Akademik\Program\Pages\CreateProgramStudi;
use App\Filament\Resources\Akademik\Program\Pages\EditProgramStudi;
use App\Filament\Resources\Akademik\Program\Pages\ListProgramStudi;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Akademik\Models\ProgramStudi;

class ProgramStudiResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = ProgramStudi::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Program & Kurikulum';

    protected static ?string $modelLabel = 'Program';

    protected static ?string $pluralModelLabel = 'Program & Kurikulum';

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 10;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Program / Kurikulum')->schema([
                TextInput::make('nama')->label('Nama Program')->required()->maxLength(255),
                TextInput::make('kode')->label('Kode')->maxLength(40),
                TextInput::make('jenjang')->label('Jenjang')->maxLength(40)->placeholder('MA / MTs / Mahad'),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
                Textarea::make('kurikulum')->label('Kurikulum')->rows(3)->columnSpanFull(),
            ])->columns(2),
            Section::make('Capaian Pembelajaran (Outcome)')->schema([
                Repeater::make('outcome')->label('Outcome')->relationship()
                    ->schema([
                        TextInput::make('kode')->label('Kode')->maxLength(40),
                        TextInput::make('deskripsi')->label('Deskripsi')->required(),
                        TextInput::make('urutan')->label('Urutan')->numeric()->default(1),
                    ])->columns(3)->defaultItems(0)->reorderable()->addActionLabel('Tambah Outcome'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Program')->searchable(),
                TextColumn::make('jenjang')->label('Jenjang')->badge()->placeholder('—'),
                TextColumn::make('outcome_count')->label('Outcome')->counts('outcome')->badge(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('nama')
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProgramStudi::route('/'),
            'create' => CreateProgramStudi::route('/create'),
            'edit' => EditProgramStudi::route('/{record}/edit'),
        ];
    }
}
