<?php

namespace App\Filament\Resources\Asrama\BiayaAsrama;

use App\Filament\Resources\Asrama\BiayaAsrama\Pages\CreateBiayaAsrama;
use App\Filament\Resources\Asrama\BiayaAsrama\Pages\EditBiayaAsrama;
use App\Filament\Resources\Asrama\BiayaAsrama\Pages\ListBiayaAsrama;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Asrama\Models\BiayaAsrama;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Kesantrian\Models\Santri;

class BiayaAsramaResource extends Resource
{
    protected static ?string $model = BiayaAsrama::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Biaya Asrama';

    protected static string|\UnitEnum|null $navigationGroup = 'Asrama';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canAny(['view_keuangan', 'manage_keuangan', 'manage_santri']) ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['view_keuangan', 'manage_keuangan', 'manage_santri']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canAny(['manage_keuangan', 'manage_santri']) ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->canAny(['manage_keuangan', 'manage_santri']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('santri_id')
                ->label('Santri')
                ->options(fn () => Santri::query()->orderBy('nama')->pluck('nama', 'id'))
                ->required()
                ->searchable(),
            Select::make('kamar_id')
                ->label('Kamar')
                ->options(fn () => KamarAsrama::query()
                    ->with('blok')
                    ->get()
                    ->mapWithKeys(fn (KamarAsrama $kamar) => [$kamar->id => $kamar->blok->nama_blok . ' - ' . $kamar->nomor_kamar]))
                ->searchable()
                ->nullable(),
            TextInput::make('periode')->required()->placeholder('2026-07'),
            TextInput::make('nominal')->numeric()->required()->minValue(0),
            Select::make('status_pembayaran')
                ->options([
                    'belum_bayar' => 'Belum Bayar',
                    'sebagian' => 'Sebagian',
                    'lunas' => 'Lunas',
                ])
                ->default('belum_bayar')
                ->required(),
            DatePicker::make('jatuh_tempo'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->searchable()->sortable(),
                TextColumn::make('periode')->sortable(),
                TextColumn::make('nominal')->money('IDR', locale: 'id'),
                TextColumn::make('status_pembayaran')->badge(),
                TextColumn::make('tagihanKeuangan.id')->label('Tagihan')->placeholder('-'),
            ])
            ->defaultSort('periode', 'desc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['santri', 'tagihanKeuangan']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBiayaAsrama::route('/'),
            'create' => CreateBiayaAsrama::route('/create'),
            'edit' => EditBiayaAsrama::route('/{record}/edit'),
        ];
    }
}

