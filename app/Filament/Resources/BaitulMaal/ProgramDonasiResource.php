<?php

namespace App\Filament\Resources\BaitulMaal;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Resources\BaitulMaal\Pages\CreateProgramDonasi;
use App\Filament\Resources\BaitulMaal\Pages\EditProgramDonasi;
use App\Filament\Resources\BaitulMaal\Pages\ListProgramDonasi;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
use Modules\Keuangan\Models\ProgramDonasi;
use Modules\Ledger\Models\SumberDana;

/**
 * 2c — Program donasi Baitul Maal (tampil di halaman publik /donasi).
 */
class ProgramDonasiResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = ProgramDonasi::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Program Donasi';

    protected static ?string $modelLabel = 'Program Donasi';

    protected static ?string $pluralModelLabel = 'Program Donasi';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 30;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('keuangan') && (auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_keuangan') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('manage_keuangan') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Program Donasi')->schema([
                TextInput::make('nama')->label('Nama Program')->required()->maxLength(255),
                Select::make('sumber_dana_id')->label('Sumber Dana')->required()->searchable()
                    ->options(fn () => SumberDana::query()->orderBy('kode_dana')->pluck('nama_sumber', 'id'))
                    ->helperText('Menentukan dimensi sumber dana pada jurnal saat donasi dikonfirmasi.'),
                TextInput::make('target_nominal')->label('Target (Rp, opsional)')->numeric()->prefix('Rp'),
                Toggle::make('aktif')->label('Aktif')->default(true)->inline(false),
                Textarea::make('deskripsi')->label('Deskripsi')->rows(3)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Program')->searchable()->sortable(),
                TextColumn::make('sumberDana.nama_sumber')->label('Sumber Dana'),
                TextColumn::make('target_nominal')->label('Target')->money('IDR')->placeholder('—'),
                TextColumn::make('donasi_count')->label('Donasi')->counts('donasi'),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->defaultSort('nama')
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProgramDonasi::route('/'),
            'create' => CreateProgramDonasi::route('/create'),
            'edit' => EditProgramDonasi::route('/{record}/edit'),
        ];
    }
}
