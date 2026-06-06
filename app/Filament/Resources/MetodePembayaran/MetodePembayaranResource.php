<?php

namespace App\Filament\Resources\MetodePembayaran;

use App\Filament\Resources\MetodePembayaran\Pages\CreateMetodePembayaran;
use App\Filament\Resources\MetodePembayaran\Pages\EditMetodePembayaran;
use App\Filament\Resources\MetodePembayaran\Pages\ListMetodePembayaran;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Keuangan\Models\MetodePembayaran;

class MetodePembayaranResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = MetodePembayaran::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Metode Pembayaran';

    protected static ?string $modelLabel = 'Metode Pembayaran';

    protected static ?string $pluralModelLabel = 'Metode Pembayaran';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('keuangan') && (auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('keuangan') && (auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_keuangan') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('manage_keuangan') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('manage_keuangan') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detail Metode')->schema([
                Select::make('tipe')
                    ->label('Tipe')
                    ->options([
                        'bank' => 'Bank',
                        'ewallet' => 'E-Wallet',
                        'qris_static' => 'QRIS Statis',
                        'qris' => 'QRIS',
                        'tunai' => 'Tunai',
                    ])
                    ->required(),
                TextInput::make('nama')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('nomor')
                    ->label('No. Rekening / VA / HP')
                    ->maxLength(255),
                TextInput::make('atas_nama')
                    ->label('Atas Nama')
                    ->maxLength(255),
                Textarea::make('instruksi')
                    ->label('Instruksi Pembayaran')
                    ->rows(3)
                    ->columnSpanFull(),
                FileUpload::make('gambar')
                    ->label('Gambar QRIS / Logo')
                    ->image()
                    ->directory('metode-pembayaran')
                    ->maxSize(2048)
                    ->columnSpanFull(),
                TextInput::make('urutan')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tipe')
                    ->label('Tipe')
                    ->badge(),
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nomor')
                    ->label('Nomor')
                    ->placeholder('-'),
                TextColumn::make('atas_nama')
                    ->label('Atas Nama')
                    ->placeholder('-'),
                TextColumn::make('urutan')
                    ->label('Urutan')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('urutan')
            ->filters([
                SelectFilter::make('tipe')
                    ->options([
                        'bank' => 'Bank',
                        'ewallet' => 'E-Wallet',
                        'qris_static' => 'QRIS Statis',
                        'qris' => 'QRIS',
                        'tunai' => 'Tunai',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMetodePembayaran::route('/'),
            'create' => CreateMetodePembayaran::route('/create'),
            'edit' => EditMetodePembayaran::route('/{record}/edit'),
        ];
    }
}
