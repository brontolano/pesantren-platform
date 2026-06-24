<?php

namespace Modules\Asrama\Filament\Resources\KamarAsrama;

use Modules\Asrama\Filament\Resources\KamarAsrama\Pages\CreateKamarAsrama;
use Modules\Asrama\Filament\Resources\KamarAsrama\Pages\EditKamarAsrama;
use Modules\Asrama\Filament\Resources\KamarAsrama\Pages\ListKamarAsrama;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Asrama\Models\BlokAsrama;
use Modules\Asrama\Models\KamarAsrama;

class KamarAsramaResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;
    protected static ?string $model = KamarAsrama::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationLabel = 'Kamar Asrama';

    protected static string|\UnitEnum|null $navigationGroup = 'Santri & Pengasuhan';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('asrama') && (auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('asrama') && (auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('blok_id')
                ->label('Blok Asrama')
                ->options(fn () => BlokAsrama::query()->orderBy('nama_blok')->pluck('nama_blok', 'id'))
                ->required()
                ->searchable(),
            TextInput::make('nomor_kamar')->required()->maxLength(50),
            TextInput::make('kapasitas')->numeric()->required()->minValue(1),
            Select::make('tipe')
                ->options([
                    'regular' => 'Regular',
                    'vip' => 'VIP',
                    'khusus' => 'Khusus',
                ])
                ->required(),
            TextInput::make('biaya_per_bulan')->numeric()->required()->minValue(0),
            Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada kamar')
            ->emptyStateDescription('Tambahkan kamar asrama untuk mulai menempatkan santri.')
            ->emptyStateIcon('heroicon-o-home-modern')
            ->columns([
                TextColumn::make('blok.nama_blok')->label('Blok')->sortable()->searchable(),
                TextColumn::make('nomor_kamar')->sortable()->searchable(),
                TextColumn::make('kapasitas')->sortable(),
                TextColumn::make('penghuni_aktif')->label('Penghuni')->state(fn (KamarAsrama $record) => $record->penempatanAktif()->count()),
                TextColumn::make('tipe')->badge(),
                TextColumn::make('biaya_per_bulan')->money('IDR', locale: 'id'),
            ])
            ->defaultSort('nomor_kamar')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('blok');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKamarAsrama::route('/'),
            'create' => CreateKamarAsrama::route('/create'),
            'edit' => EditKamarAsrama::route('/{record}/edit'),
        ];
    }
}

