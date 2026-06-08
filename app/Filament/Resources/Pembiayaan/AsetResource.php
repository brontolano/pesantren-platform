<?php

namespace App\Filament\Resources\Pembiayaan;

use App\Filament\Resources\Pembiayaan\Pages\CreateAset;
use App\Filament\Resources\Pembiayaan\Pages\EditAset;
use App\Filament\Resources\Pembiayaan\Pages\ListAset;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Pembiayaan\Models\Aset;

class AsetResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = Aset::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Aset';

    protected static ?string $modelLabel = 'Aset';

    protected static ?string $pluralModelLabel = 'Aset';

    protected static string|\UnitEnum|null $navigationGroup = 'Pembiayaan';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('pembiayaan') && (auth()->user()?->can('manage_pembiayaan') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Aset')->schema([
                TextInput::make('nama_aset')->label('Nama Aset')->required()->maxLength(255),
                TextInput::make('jenis')->label('Jenis')->placeholder('Tanah / Bangunan / Kendaraan')->maxLength(255),
                TextInput::make('nilai')->label('Nilai (Rp)')->numeric()->prefix('Rp')->default(0),
                TextInput::make('nomor_sertifikat')->label('Nomor Sertifikat')->maxLength(255),
                Toggle::make('is_wakaf')->label('Aset Wakaf (terkunci dari agunan)')
                    ->helperText('UU 41/2004 Ps 40 — aset wakaf tidak dapat dijadikan agunan.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_aset')->label('Nama')->searchable(),
                TextColumn::make('jenis')->label('Jenis')->badge(),
                TextColumn::make('nilai')->label('Nilai')->money('IDR'),
                IconColumn::make('is_wakaf')->label('Wakaf')->boolean(),
                TextColumn::make('nomor_sertifikat')->label('Sertifikat')->placeholder('—'),
            ])
            ->defaultSort('nama_aset')
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAset::route('/'),
            'create' => CreateAset::route('/create'),
            'edit' => EditAset::route('/{record}/edit'),
        ];
    }
}
