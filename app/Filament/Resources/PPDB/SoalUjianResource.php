<?php

namespace App\Filament\Resources\PPDB;

use App\Filament\Resources\PPDB\SoalUjian\Pages\CreateSoalUjian;
use App\Filament\Resources\PPDB\SoalUjian\Pages\EditSoalUjian;
use App\Filament\Resources\PPDB\SoalUjian\Pages\ListSoalUjian;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\PPDB\Models\SoalUjian;

/**
 * Authoring bank soal ujian PPDB (panitia). CRUD SoalUjian per kategori_ujian;
 * auto-grading PG ditangani SoalUjian::nilaiUntukJawaban + UjianScoringService.
 */
class SoalUjianResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = SoalUjian::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Bank Soal';

    protected static ?string $modelLabel = 'Soal Ujian';

    protected static ?string $pluralModelLabel = 'Bank Soal';

    protected static string|\UnitEnum|null $navigationGroup = 'PPDB';

    protected static ?string $slug = 'ppdb/bank-soal';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('ppdb') && static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('ppdb') && (Auth::user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Soal')->schema([
                TextInput::make('kategori_ujian')
                    ->label('Kategori Ujian')
                    ->required()
                    ->maxLength(100)
                    ->helperText('Pengelompokan paket ujian, mis. akademik / tahfidz.'),
                Select::make('tipe')
                    ->label('Tipe')
                    ->options(SoalUjian::TIPE_LABELS)
                    ->default(SoalUjian::TIPE_PG)
                    ->required(),
                Textarea::make('pertanyaan')
                    ->label('Pertanyaan')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
                KeyValue::make('pilihan')
                    ->label('Pilihan (Pilihan Ganda)')
                    ->keyLabel('Opsi')
                    ->valueLabel('Teks')
                    ->helperText('Isi untuk tipe Pilihan Ganda, mis. A = …, B = …. Kosongkan untuk essay.')
                    ->columnSpanFull(),
                TextInput::make('kunci_jawaban')
                    ->label('Kunci Jawaban (PG)')
                    ->maxLength(10)
                    ->helperText('Mis. A / B / C. Kosongkan untuk essay (dinilai manual).'),
                TextInput::make('bobot')
                    ->label('Bobot')
                    ->numeric()
                    ->default(1)
                    ->minValue(0)
                    ->required(),
                Toggle::make('aktif')
                    ->label('Aktif')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kategori_ujian')
                    ->label('Kategori')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('pertanyaan')
                    ->label('Pertanyaan')
                    ->limit(50)
                    ->wrap(),
                TextColumn::make('tipe')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => SoalUjian::TIPE_LABELS[$state] ?? (string) $state),
                TextColumn::make('bobot')
                    ->label('Bobot')
                    ->sortable(),
                IconColumn::make('aktif')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSoalUjian::route('/'),
            'create' => CreateSoalUjian::route('/create'),
            'edit' => EditSoalUjian::route('/{record}/edit'),
        ];
    }
}
